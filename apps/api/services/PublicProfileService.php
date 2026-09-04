<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\ProfilePhotoStorage;
use app\components\PublicContentSanitizer;
use app\components\PublicSlug;
use app\components\PublicWebsiteFields;
use app\models\Instructor;
use app\models\Organisation;
use app\models\ProfileSlugRedirect;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Public instructor profile — editor, publish, and public serialization.
 */
class PublicProfileService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_UNPUBLISHED = 'unpublished';

    public const ACQUISITION_OPEN = 'open';
    public const ACQUISITION_LIMITED = 'limited';
    public const ACQUISITION_WAITING_LIST = 'waiting_list';
    public const ACQUISITION_CLOSED = 'closed';

    private ProfilePhotoStorage $photos;

    public function __construct(?ProfilePhotoStorage $photos = null)
    {
        $this->photos = $photos ?? new ProfilePhotoStorage();
    }

    /**
     * @return array<string, mixed>
     */
    public function editorSettings(Organisation $org, Instructor $instructor): array
    {
        return [
            'profile' => $this->editorBlock($org, $instructor),
            'publish_blockers' => $this->publishBlockers($org),
            'acquisition_options' => $this->acquisitionOptions(),
            'transmission_options' => $this->transmissionOptions(),
            'adi_status_options' => $this->adiStatusOptions(),
            'source_link_tags' => ['instagram', 'facebook', 'website', 'tiktok'],
            'teaching_style_options' => $this->teachingStyleOptions(),
            'service_type_options' => $this->serviceTypeOptions(),
            'analytics' => (new PublicAnalyticsService())->summaryForOrganisation($org),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(Organisation $org, Instructor $instructor, array $data): array
    {
        if (array_key_exists('intro', $data)) {
            $intro = trim((string) $data['intro']);
            $org->profile_intro = $intro === '' ? null : mb_substr($intro, 0, 4000);
        }
        if (array_key_exists('transmission', $data)) {
            $tx = trim((string) ($data['transmission'] ?? ''));
            $org->profile_transmission = in_array($tx, ['manual', 'automatic', 'both'], true) ? $tx : null;
        }
        if (array_key_exists('teaching_areas', $data)) {
            $org->profile_teaching_areas = $this->encodeAreas($data['teaching_areas']);
        }
        if (array_key_exists('languages', $data)) {
            $langs = trim((string) ($data['languages'] ?? ''));
            $org->profile_languages = $langs === '' ? null : mb_substr($langs, 0, 255);
        }
        if (array_key_exists('adi_status', $data)) {
            $adi = trim((string) ($data['adi_status'] ?? ''));
            $org->profile_adi_status = in_array($adi, ['adi', 'pdi', 'none'], true) ? $adi : null;
        }
        if (array_key_exists('years_teaching', $data)) {
            $years = $data['years_teaching'];
            $org->profile_years_teaching = $years === null || $years === '' ? null : max(0, min(60, (int) $years));
        }
        if (array_key_exists('vehicle_summary', $data)) {
            $vehicle = trim((string) ($data['vehicle_summary'] ?? ''));
            $org->profile_vehicle_summary = $vehicle === '' ? null : mb_substr($vehicle, 0, 255);
        }
        if (array_key_exists('public_pricing', $data)) {
            $org->profile_public_pricing = $this->encodePricing($data['public_pricing']);
        }
        if (array_key_exists('services', $data)) {
            $services = PublicWebsiteFields::encodeServices($data['services']);
            $org->profile_services = $services === [] ? null : json_encode($services, JSON_THROW_ON_ERROR);
            $org->profile_public_pricing = PublicWebsiteFields::servicesToLegacyPricing($services);
        }
        if (array_key_exists('business_name', $data)) {
            $org->profile_business_name = PublicContentSanitizer::singleLine((string) ($data['business_name'] ?? ''), 255);
        }
        if (array_key_exists('accent_colour', $data)) {
            $org->profile_accent_colour = PublicContentSanitizer::accentColour(
                $data['accent_colour'] === '' ? null : (string) $data['accent_colour'],
            );
        }
        if (array_key_exists('teaching_styles', $data)) {
            $styles = PublicWebsiteFields::encodeTeachingStyles($data['teaching_styles']);
            $org->profile_teaching_styles = $styles === [] ? null : json_encode($styles, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('faqs', $data)) {
            $faqs = PublicWebsiteFields::encodeFaqs($data['faqs']);
            $org->profile_faqs = $faqs === [] ? null : json_encode($faqs, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('social_links', $data)) {
            $links = PublicWebsiteFields::encodeSocialLinks($data['social_links']);
            $org->profile_social_links = $links === [] ? null : json_encode($links, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('contact_phone', $data)) {
            $phone = trim((string) ($data['contact_phone'] ?? ''));
            $org->profile_contact_phone = $phone === '' ? null : mb_substr($phone, 0, 32);
        }
        if (array_key_exists('contact_email', $data)) {
            $email = trim((string) ($data['contact_email'] ?? ''));
            $org->profile_contact_email = $email === '' ? null : mb_substr($email, 0, 255);
        }
        if (array_key_exists('whatsapp', $data)) {
            $wa = trim((string) ($data['whatsapp'] ?? ''));
            $org->profile_whatsapp = $wa === '' ? null : mb_substr($wa, 0, 32);
        }
        if (array_key_exists('show_phone', $data)) {
            $org->profile_show_phone = (bool) $data['show_phone'];
        }
        if (array_key_exists('show_email', $data)) {
            $org->profile_show_email = (bool) $data['show_email'];
        }
        if (array_key_exists('dual_controls', $data)) {
            $org->profile_dual_controls = (bool) $data['dual_controls'];
        }
        if (array_key_exists('allow_indexing', $data)) {
            $org->profile_allow_indexing = (bool) $data['allow_indexing'];
        }
        if (array_key_exists('acquisition_mode', $data)) {
            $mode = trim((string) $data['acquisition_mode']);
            if (!in_array($mode, [
                self::ACQUISITION_OPEN,
                self::ACQUISITION_LIMITED,
                self::ACQUISITION_WAITING_LIST,
                self::ACQUISITION_CLOSED,
            ], true)) {
                throw new BadRequestHttpException('Choose a valid new-pupil status.');
            }
            $org->profile_acquisition_mode = $mode;
        }
        if (array_key_exists('allow_waiting_list', $data)) {
            $org->profile_allow_waiting_list = (bool) $data['allow_waiting_list'];
        }
        if (array_key_exists('slug', $data)) {
            $this->assignSlug($org, trim((string) $data['slug']));
        } elseif ($org->profile_slug === null || $org->profile_slug === '') {
            $org->profile_slug = PublicSlug::fromName($instructor->display_name, (int) $org->id);
        }

        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false);

        return $this->editorSettings($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(Organisation $org, Instructor $instructor): array
    {
        $blockers = $this->publishBlockers($org);
        if ($blockers !== []) {
            throw new BadRequestHttpException('Complete your profile before publishing: ' . implode('; ', $blockers));
        }
        if ($org->profile_slug === null || $org->profile_slug === '') {
            $org->profile_slug = PublicSlug::fromName($instructor->display_name, (int) $org->id);
        }
        $org->profile_status = self::STATUS_PUBLISHED;
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['profile_slug', 'profile_status', 'updated_at']);

        return $this->editorSettings($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function unpublish(Organisation $org, Instructor $instructor): array
    {
        $org->profile_status = self::STATUS_UNPUBLISHED;
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['profile_status', 'updated_at']);

        return $this->editorSettings($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadPhoto(Organisation $org, Instructor $instructor): array
    {
        /** @var UploadedFile|null $file */
        $file = UploadedFile::getInstanceByName('photo');
        if ($file === null) {
            throw new BadRequestHttpException('Choose a photo to upload.');
        }
        if ($org->profile_photo_path) {
            $this->photos->delete((string) $org->profile_photo_path);
        }
        $stored = $this->photos->store((int) $org->id, $file);
        $org->profile_photo_path = $stored['path'];
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['profile_photo_path', 'updated_at']);

        return $this->editorSettings($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadCover(Organisation $org, Instructor $instructor): array
    {
        /** @var UploadedFile|null $file */
        $file = UploadedFile::getInstanceByName('cover');
        if ($file === null) {
            throw new BadRequestHttpException('Choose a cover image to upload.');
        }
        if ($org->profile_cover_path) {
            $this->photos->delete((string) $org->profile_cover_path);
        }
        $stored = $this->photos->storeCover((int) $org->id, $file);
        $org->profile_cover_path = $stored['path'];
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['profile_cover_path', 'updated_at']);

        return $this->editorSettings($org, $instructor);
    }

    public function publicBySlug(string $slug, bool $preview = false): array
    {
        $org = $this->resolveOrganisationBySlug($slug);
        if (!$preview && $org->profile_status !== self::STATUS_PUBLISHED) {
            throw new NotFoundHttpException('Profile not found.');
        }

        return $this->publicSerialize($org, $preview);
    }

    public function findPublishedOrganisation(string $slug): Organisation
    {
        $org = $this->resolveOrganisationBySlug($slug);
        if ($org->profile_status !== self::STATUS_PUBLISHED) {
            throw new NotFoundHttpException('Profile not found.');
        }

        return $org;
    }

    public function photoResponse(string $slug): array
    {
        $org = $this->findPublishedOrganisation($slug);
        if ($org->profile_photo_path === null || $org->profile_photo_path === '') {
            throw new NotFoundHttpException('Photo not found.');
        }

        return [
            'content' => $this->photos->read((string) $org->profile_photo_path),
            'mime' => $this->photos->mimeType((string) $org->profile_photo_path),
        ];
    }

    public function coverResponse(string $slug): array
    {
        $org = $this->findPublishedOrganisation($slug);
        if ($org->profile_cover_path === null || $org->profile_cover_path === '') {
            throw new NotFoundHttpException('Cover not found.');
        }

        return [
            'content' => $this->photos->read((string) $org->profile_cover_path),
            'mime' => $this->photos->mimeType((string) $org->profile_cover_path),
        ];
    }

    public function editorCoverResponse(Organisation $org): array
    {
        if ($org->profile_cover_path === null || $org->profile_cover_path === '') {
            throw new NotFoundHttpException('Cover not found.');
        }

        return [
            'content' => $this->photos->read((string) $org->profile_cover_path),
            'mime' => $this->photos->mimeType((string) $org->profile_cover_path),
        ];
    }

    /**
     * @return list<string>
     */
    public function publishedSlugs(): array
    {
        /** @var Organisation[] $orgs */
        $orgs = Organisation::find()
            ->andWhere(['profile_status' => self::STATUS_PUBLISHED])
            ->andWhere(['not', ['profile_slug' => null]])
            ->all();

        return array_values(array_filter(array_map(
            static fn (Organisation $o) => (string) $o->profile_slug,
            $orgs,
        )));
    }

    private function resolveOrganisationBySlug(string $slug): Organisation
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || PublicSlug::isReserved($slug)) {
            throw new NotFoundHttpException('Profile not found.');
        }

        /** @var Organisation|null $org */
        $org = Organisation::find()->andWhere(['profile_slug' => $slug])->one();
        if ($org !== null) {
            return $org;
        }

        /** @var ProfileSlugRedirect|null $redirect */
        $redirect = ProfileSlugRedirect::find()->andWhere(['old_slug' => $slug])->one();
        if ($redirect !== null) {
            $org = Organisation::findOne(['id' => (int) $redirect->organisation_id]);
            if ($org !== null && $org->profile_slug) {
                return $org;
            }
        }

        throw new NotFoundHttpException('Profile not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSerialize(Organisation $org, bool $preview): array
    {
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        $mode = $this->acquisitionMode($org);
        $services = PublicWebsiteFields::decodeServices($org);
        $pricing = $this->pricingFromServices($services);
        $areas = $this->decodeAreas($org);
        $transmissionLabel = $this->transmissionLabel($org->profile_transmission);
        $displayName = $this->publicDisplayName($org, $instructor);
        $slug = (string) $org->profile_slug;
        $accent = PublicContentSanitizer::accentColour($org->profile_accent_colour) ?? '#2D6A4F';
        $shareUrl = $this->shareUrl($slug);
        $pricingFrom = $this->pricingFromLabel($services);
        $faqs = PublicWebsiteFields::decodeFaqs($org, $areas, $transmissionLabel);
        $social = PublicWebsiteFields::decodeSocialLinks($org);
        $whatsapp = PublicContentSanitizer::whatsappLink($org->profile_whatsapp);
        $indexable = $preview === false
            && $org->profile_status === self::STATUS_PUBLISHED
            && (bool) ($org->profile_allow_indexing ?? true);

        return [
            'slug' => $slug,
            'preview' => $preview,
            'display_name' => $displayName,
            'business_name' => $this->businessName($org, $instructor),
            'headline' => $this->siteHeadline($transmissionLabel, $areas),
            'subheadline' => PublicContentSanitizer::text($org->profile_intro, 200),
            'intro' => PublicContentSanitizer::text($org->profile_intro),
            'photo_url' => ($org->profile_photo_path && ($preview || $org->profile_status === self::STATUS_PUBLISHED))
                ? '/api/public/instructors/' . rawurlencode($slug) . '/photo'
                : null,
            'cover_url' => ($org->profile_cover_path && ($preview || $org->profile_status === self::STATUS_PUBLISHED))
                ? '/api/public/instructors/' . rawurlencode($slug) . '/cover'
                : null,
            'branding' => [
                'accent_colour' => $accent,
                'accent_foreground' => PublicContentSanitizer::accentForeground($accent),
            ],
            'transmission' => $transmissionLabel,
            'transmission_code' => $org->profile_transmission,
            'acquisition' => $mode,
            'acquisition_label' => $mode['label'],
            'allows_enquiry' => $mode['allows_enquiry'],
            'allows_waiting_list' => $mode['allows_waiting_list'],
            'cta_label' => PublicWebsiteFields::ctaLabel($mode),
            'teaching_areas' => $areas,
            'teaching_styles' => PublicWebsiteFields::decodeTeachingStyleLabels($org),
            'languages' => PublicContentSanitizer::singleLine($org->profile_languages, 255),
            'adi_status' => $this->adiLabel($org->profile_adi_status),
            'years_teaching' => $org->profile_years_teaching,
            'vehicle_summary' => PublicContentSanitizer::singleLine($org->profile_vehicle_summary, 255),
            'dual_controls' => (bool) ($org->profile_dual_controls ?? false),
            'services' => $services,
            'pricing' => $pricing,
            'pricing_from_label' => $pricingFrom,
            'faqs' => $faqs,
            'contact' => $this->publicContact($org, $whatsapp),
            'social_links' => $social,
            'share_url' => $shareUrl,
            'seo' => [
                'title' => PublicWebsiteFields::seoTitle($displayName, $transmissionLabel, $areas),
                'description' => PublicWebsiteFields::seoDescription($displayName, $org->profile_intro, $areas, $pricingFrom),
                'canonical' => $shareUrl,
                'robots' => $indexable ? 'index,follow' : 'noindex,nofollow',
                'og_image' => ($org->profile_photo_path && ($preview || $org->profile_status === self::STATUS_PUBLISHED))
                    ? '/api/public/instructors/' . rawurlencode($slug) . '/photo'
                    : null,
            ],
            'structured_data' => $this->structuredData($org, $instructor, $displayName, $areas, $services, $shareUrl, $transmissionLabel),
            'powered_by_ownlane' => true,
            'updated_at' => $org->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorBlock(Organisation $org, Instructor $instructor): array
    {
        $slug = (string) ($org->profile_slug ?? '');
        $mode = $this->acquisitionMode($org);
        $areas = $this->decodeAreas($org);
        $services = PublicWebsiteFields::decodeServices($org);
        $rawServices = trim((string) ($org->profile_services ?? ''));
        $editorServices = $rawServices !== ''
            ? (json_decode($rawServices, true) ?: [])
            : $services;
        $rawFaqs = trim((string) ($org->profile_faqs ?? ''));
        $faqs = $rawFaqs !== ''
            ? (json_decode($rawFaqs, true) ?: [])
            : PublicWebsiteFields::defaultFaqs($org, $areas, $this->transmissionLabel($org->profile_transmission));
        $rawStyles = trim((string) ($org->profile_teaching_styles ?? ''));
        $teachingStyles = is_array(json_decode($rawStyles ?: '[]', true))
            ? json_decode($rawStyles ?: '[]', true)
            : [];

        return [
            'status' => $org->profile_status ?? self::STATUS_DRAFT,
            'slug' => $slug,
            'share_url' => $slug !== '' ? $this->shareUrl($slug) : null,
            'display_name' => $instructor->display_name,
            'business_name' => $org->profile_business_name,
            'intro' => $org->profile_intro,
            'accent_colour' => $org->profile_accent_colour,
            'transmission' => $org->profile_transmission,
            'teaching_areas' => $areas,
            'teaching_styles' => $teachingStyles,
            'languages' => $org->profile_languages,
            'adi_status' => $org->profile_adi_status,
            'years_teaching' => $org->profile_years_teaching,
            'vehicle_summary' => $org->profile_vehicle_summary,
            'dual_controls' => (bool) ($org->profile_dual_controls ?? false),
            'public_pricing' => $this->decodePricing($org),
            'services' => $editorServices,
            'faqs' => $faqs,
            'social_links' => PublicWebsiteFields::decodeSocialLinks($org),
            'contact_phone' => $org->profile_contact_phone,
            'contact_email' => $org->profile_contact_email,
            'whatsapp' => $org->profile_whatsapp,
            'show_phone' => (bool) ($org->profile_show_phone ?? false),
            'show_email' => (bool) ($org->profile_show_email ?? false),
            'allow_indexing' => (bool) ($org->profile_allow_indexing ?? true),
            'acquisition_mode' => $org->profile_acquisition_mode ?? self::ACQUISITION_CLOSED,
            'acquisition_label' => $mode['label'],
            'allow_waiting_list' => (bool) ($org->profile_allow_waiting_list ?? true),
            'photo_url' => $org->profile_photo_path
                ? '/api/settings/profile/photo'
                : null,
            'cover_url' => $org->profile_cover_path
                ? '/api/settings/profile/cover'
                : null,
            'updated_at' => $org->updated_at,
        ];
    }

    /**
     * @return list<string>
     */
    private function publishBlockers(Organisation $org): array
    {
        $blockers = [];
        if ($this->decodeAreas($org) === []) {
            $blockers[] = 'Add at least one teaching area';
        }
        if (PublicWebsiteFields::decodeServices($org) === []) {
            $blockers[] = 'Add at least one lesson price';
        }
        if ($org->profile_transmission === null || $org->profile_transmission === '') {
            $blockers[] = 'Choose manual or automatic';
        }

        return $blockers;
    }

    private function assignSlug(Organisation $org, string $slug): void
    {
        $slug = PublicSlug::normalize($slug);
        if ($slug === '') {
            throw new BadRequestHttpException('Choose a URL for your page.');
        }
        if (PublicSlug::isReserved($slug)) {
            throw new BadRequestHttpException('That URL is not available.');
        }
        if (PublicSlug::isTaken($slug, (int) $org->id)) {
            throw new BadRequestHttpException('That URL is already taken.');
        }
        $old = strtolower(trim((string) ($org->profile_slug ?? '')));
        if ($old !== '' && $old !== $slug) {
            PublicSlug::recordRedirect((int) $org->id, $old);
        }
        $org->profile_slug = $slug;
    }

    /**
     * @return array{code: string, label: string, allows_enquiry: bool, allows_waiting_list: bool}
     */
    private function acquisitionMode(Organisation $org): array
    {
        $mode = trim((string) ($org->profile_acquisition_mode ?? self::ACQUISITION_CLOSED));
        $allowWaiting = (bool) ($org->profile_allow_waiting_list ?? true);

        return match ($mode) {
            self::ACQUISITION_OPEN => [
                'code' => $mode,
                'label' => 'Taking new pupils',
                'allows_enquiry' => true,
                'allows_waiting_list' => $allowWaiting,
            ],
            self::ACQUISITION_LIMITED => [
                'code' => $mode,
                'label' => 'Limited availability',
                'allows_enquiry' => true,
                'allows_waiting_list' => $allowWaiting,
            ],
            self::ACQUISITION_WAITING_LIST => [
                'code' => $mode,
                'label' => 'Waiting list',
                'allows_enquiry' => false,
                'allows_waiting_list' => true,
            ],
            default => [
                'code' => self::ACQUISITION_CLOSED,
                'label' => 'Not taking new pupils',
                'allows_enquiry' => false,
                'allows_waiting_list' => $allowWaiting,
            ],
        };
    }

    /**
     * @param mixed $raw
     */
    private function encodeAreas(mixed $raw): ?string
    {
        if (!is_array($raw)) {
            return null;
        }
        $areas = [];
        foreach ($raw as $line) {
            $text = trim((string) $line);
            if ($text !== '') {
                $areas[] = mb_substr($text, 0, 120);
            }
        }

        return $areas === [] ? null : json_encode(array_values(array_unique($areas)), JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function decodeAreas(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_teaching_areas ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        }

        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $decoded)));
    }

    /**
     * @param mixed $raw
     */
    private function encodePricing(mixed $raw): ?string
    {
        if (!is_array($raw)) {
            return null;
        }
        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mins = (int) ($row['duration_minutes'] ?? 0);
            $pence = (int) ($row['price_pence'] ?? 0);
            if ($mins < 15 || $pence <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $rows[] = [
                'duration_minutes' => $mins,
                'price_pence' => $pence,
                'label' => $label === '' ? null : mb_substr($label, 0, 64),
            ];
        }

        return $rows === [] ? null : json_encode($rows, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{duration_minutes: int, price_pence: int, label: string|null, price_label: string}>
     */
    private function decodePricing(Organisation $org): array
    {
        $raw = trim((string) ($org->profile_public_pricing ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mins = (int) ($row['duration_minutes'] ?? 0);
            $pence = (int) ($row['price_pence'] ?? 0);
            if ($mins < 15 || $pence <= 0) {
                continue;
            }
            $rows[] = [
                'duration_minutes' => $mins,
                'price_pence' => $pence,
                'label' => isset($row['label']) ? (string) $row['label'] : null,
                'price_label' => Money::formatPence($pence),
            ];
        }
        usort($rows, static fn ($a, $b) => $a['duration_minutes'] <=> $b['duration_minutes']);

        return $rows;
    }

  private function pricingFromLabel(array $services): ?string
    {
        if ($services === []) {
            return null;
        }
        $min = $services[0];
        $duration = $min['duration_label'] ?? ($min['duration_minutes'] ? $min['duration_minutes'] . ' min' : null);

        return $duration
            ? $duration . ' from ' . $min['price_label']
            : 'From ' . $min['price_label'];
    }

    /**
     * @param list<array<string, mixed>> $services
     * @return list<array{duration_minutes: int, price_pence: int, label: string|null, price_label: string}>
     */
    private function pricingFromServices(array $services): array
    {
        $rows = [];
        foreach ($services as $service) {
            if (($service['type'] ?? 'lesson') !== 'lesson') {
                continue;
            }
            $mins = (int) ($service['duration_minutes'] ?? 0);
            if ($mins < 15) {
                continue;
            }
            $rows[] = [
                'duration_minutes' => $mins,
                'price_pence' => (int) $service['price_pence'],
                'label' => $service['name'] ?? null,
                'price_label' => (string) $service['price_label'],
            ];
        }

        return $rows;
    }

    private function publicDisplayName(Organisation $org, ?Instructor $instructor): string
    {
        $business = PublicContentSanitizer::singleLine($org->profile_business_name, 255);
        if ($business !== null) {
            return $business;
        }

        return $instructor?->display_name ?? $org->name;
    }

    private function businessName(Organisation $org, ?Instructor $instructor): ?string
    {
        $business = PublicContentSanitizer::singleLine($org->profile_business_name, 255);
        if ($business !== null && $business !== ($instructor?->display_name ?? '')) {
            return $business;
        }

        return null;
    }

    /**
     * @param list<string> $areas
     */
    private function siteHeadline(?string $transmissionLabel, array $areas): string
    {
        $place = $areas[0] ?? 'your area';
        if ($transmissionLabel) {
            return $transmissionLabel . ' driving lessons in ' . $place;
        }

        return 'Driving lessons in ' . $place;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicContact(Organisation $org, ?string $whatsapp): array
    {
        $contact = [];
        if ((bool) ($org->profile_show_phone ?? false) && $org->profile_contact_phone) {
            $contact['phone'] = (string) $org->profile_contact_phone;
        }
        if ((bool) ($org->profile_show_email ?? false) && $org->profile_contact_email) {
            $contact['email'] = (string) $org->profile_contact_email;
        }
        if ($whatsapp !== null) {
            $contact['whatsapp_url'] = $whatsapp;
        }

        return $contact;
    }

    /**
     * @param list<string> $areas
     * @param list<array<string, mixed>> $services
     * @return array<string, mixed>
     */
    private function structuredData(
        Organisation $org,
        ?Instructor $instructor,
        string $displayName,
        array $areas,
        array $services,
        string $shareUrl,
        ?string $transmissionLabel,
    ): array {
        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Person',
                    'name' => $displayName,
                    'url' => $shareUrl,
                    'description' => PublicContentSanitizer::text($org->profile_intro, 300),
                ],
                [
                    '@type' => 'LocalBusiness',
                    'name' => $displayName,
                    'url' => $shareUrl,
                    'areaServed' => array_map(static fn (string $a) => ['@type' => 'Place', 'name' => $a], $areas),
                ],
            ],
        ];

        foreach ($services as $service) {
            $graph['@graph'][] = [
                '@type' => 'Service',
                'name' => $service['name'],
                'description' => $service['description'] ?? null,
                'provider' => ['@type' => 'Person', 'name' => $displayName],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format(((int) $service['price_pence']) / 100, 2, '.', ''),
                    'priceCurrency' => 'GBP',
                ],
            ];
        }

        if ($transmissionLabel) {
            $graph['@graph'][1]['description'] = $transmissionLabel . ' driving lessons';
        }

        return $graph;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teachingStyleOptions(): array
    {
        $options = [];
        foreach (PublicWebsiteFields::TEACHING_STYLE_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function serviceTypeOptions(): array
    {
        return [
            ['value' => 'lesson', 'label' => 'Driving lesson'],
            ['value' => 'mock_test', 'label' => 'Mock test'],
            ['value' => 'refresher', 'label' => 'Refresher lesson'],
            ['value' => 'motorway', 'label' => 'Motorway lesson'],
            ['value' => 'test_day', 'label' => 'Test day lesson'],
        ];
    }

    /**
     * @param list<string> $areas
     */
    private function headline(Organisation $org, ?Instructor $instructor, array $areas): string
    {
        $name = $instructor?->display_name ?? $org->name;
        $place = $areas[0] ?? ($org->service_area ? strtok((string) $org->service_area, ',') : 'your area');

        return 'Driving instructor in ' . trim((string) $place);
    }

    private function transmissionLabel(?string $code): ?string
    {
        return match ($code) {
            'manual' => 'Manual',
            'automatic' => 'Automatic',
            'both' => 'Manual and automatic',
            default => null,
        };
    }

    private function adiLabel(?string $code): ?string
    {
        return match ($code) {
            'adi' => 'ADI (self-reported)',
            'pdi' => 'PDI (self-reported)',
            default => null,
        };
    }

  private function shareUrl(string $slug): string
    {
        $base = rtrim((string) (getenv('WEB_URL') ?: 'http://127.0.0.1:3000'), '/');

        return $base . '/instructors/' . rawurlencode($slug);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function acquisitionOptions(): array
    {
        return [
            ['value' => self::ACQUISITION_OPEN, 'label' => 'Taking new pupils'],
            ['value' => self::ACQUISITION_LIMITED, 'label' => 'Limited availability'],
            ['value' => self::ACQUISITION_WAITING_LIST, 'label' => 'Waiting list only'],
            ['value' => self::ACQUISITION_CLOSED, 'label' => 'Not taking new pupils'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function transmissionOptions(): array
    {
        return [
            ['value' => 'automatic', 'label' => 'Automatic'],
            ['value' => 'manual', 'label' => 'Manual'],
            ['value' => 'both', 'label' => 'Manual and automatic'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function adiStatusOptions(): array
    {
        return [
            ['value' => 'adi', 'label' => 'ADI'],
            ['value' => 'pdi', 'label' => 'PDI'],
            ['value' => 'none', 'label' => 'Prefer not to say'],
        ];
    }
}

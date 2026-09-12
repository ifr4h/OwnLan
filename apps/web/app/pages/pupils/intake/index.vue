<script setup lang="ts">
/**
 * Intake open-links list removed — waitlist lives on Pupils as status “On waitlist”.
 * Create links via Add pupil → Send a link; review submitted intakes from Today.
 */
await navigateTo({ path: '/pupils', query: { status: 'waiting' } }, { replace: true })
</script>

<template>
  <FeaturePageLayout v-if="page" :copy="page.copy">
    <template #demo>
      <component :is="page.demo" />
    </template>
  </FeaturePageLayout>
</template>

<script setup lang="ts">
import DemoDiaryDay from '~/components/marketing/demo/DemoDiaryDay.vue'
import DemoMoneyOverview from '~/components/marketing/demo/DemoMoneyOverview.vue'
import DemoProgressTimeline from '~/components/marketing/demo/DemoProgressTimeline.vue'
import DemoPupilCard from '~/components/marketing/demo/DemoPupilCard.vue'
import DemoTeachingBoard from '~/components/marketing/demo/DemoTeachingBoard.vue'
import { featurePageCopy } from '~/marketing/content/copy/feature-pages'

definePageMeta({ layout: 'marketing' })

const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))

const pages: Record<string, { copy: typeof featurePageCopy.diary; demo: Component }> = {
  diary: { copy: featurePageCopy.diary, demo: DemoDiaryDay },
  pupils: { copy: featurePageCopy.pupils, demo: DemoPupilCard },
  progress: { copy: featurePageCopy.progress, demo: DemoProgressTimeline },
  teaching: { copy: featurePageCopy.teaching, demo: DemoTeachingBoard },
  money: { copy: featurePageCopy.money, demo: DemoMoneyOverview },
}

const page = computed(() => pages[slug.value])

if (!pages[String(route.params.slug ?? '')]) {
  throw createError({ statusCode: 404, statusMessage: 'Feature not found' })
}

watch(page, (p) => {
  if (p) {
    useMarketingSeo({
      title: p.copy.meta.title,
      description: p.copy.meta.description,
      path: `/features/${slug.value}`,
    })
  }
}, { immediate: true })
</script>

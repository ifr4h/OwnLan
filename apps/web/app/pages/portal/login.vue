<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const route = useRoute()

await navigateTo({
  path: '/login',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
})
</script>

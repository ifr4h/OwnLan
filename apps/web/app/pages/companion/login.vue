<script setup lang="ts">
definePageMeta({ layout: 'companion' })

const route = useRoute()

await navigateTo({
  path: '/login',
  query: route.query.redirect ? { redirect: route.query.redirect } : {},
})
</script>

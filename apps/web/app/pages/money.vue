<script setup lang="ts">
const route = useRoute()

await navigateTo({ path: '/accounts', query: route.query })
</script>

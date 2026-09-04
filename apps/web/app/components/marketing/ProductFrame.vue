<template>
  <div class="product-frame" :class="{ 'product-frame--phone': phone }">
    <div v-if="!phone" class="product-frame__bar">
      <span class="product-frame__dot" />
      <span class="product-frame__dot" />
      <span class="product-frame__dot" />
      <span v-if="title" class="product-frame__title">{{ title }}</span>
    </div>
    <div class="product-frame__body" :class="{ 'product-frame__body--flush': flush }">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  phone?: boolean
  flush?: boolean
}>()
</script>

<style scoped>
.product-frame--phone {
  border: none;
  box-shadow: none;
  background: transparent;
}

.product-frame__body--flush {
  padding: 0;
}
</style>

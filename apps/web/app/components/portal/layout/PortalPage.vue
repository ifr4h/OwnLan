<template>
  <div class="portal-page">
    <header v-if="$slots.header" class="portal-page__header">
      <slot name="header" />
    </header>
    <slot />
  </div>
</template>

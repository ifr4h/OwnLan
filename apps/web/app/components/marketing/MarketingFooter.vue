<template>
  <footer class="mfooter marketing-section--tight">
    <div class="mfooter__inner marketing-shell">
      <div class="mfooter__brand">
        <p class="mfooter__name">{{ site.name }}</p>
        <p class="mfooter__tag">{{ site.tagline }}</p>
      </div>

      <div class="mfooter__cols">
        <div v-for="(group, key) in groups" :key="key">
          <p class="mfooter__label">{{ group.label }}</p>
          <ul class="mfooter__list">
            <li v-for="link in group.links" :key="link.to">
              <NuxtLink :to="link.to">{{ link.label }}</NuxtLink>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div class="mfooter__legal marketing-shell">
      <p>© {{ year }} OwnLane. UK driving instructor platform.</p>
      <p class="mfooter__beta-note">Beta — features and pricing may change.</p>
    </div>
  </footer>
</template>

<script setup lang="ts">
import { marketingSite as site } from '~/marketing/content/site'

const year = new Date().getFullYear()

const groups = [
  { label: 'Product', links: site.footer.product },
  { label: 'Company', links: site.footer.company },
  { label: 'Account', links: site.footer.legal },
]
</script>

<style scoped>
.mfooter {
  background: var(--color-frost-green);
  border-top: 1px solid var(--color-border);
}

.mfooter__inner {
  display: grid;
  gap: var(--spacing-32);
  padding-block: var(--spacing-40);
}

.mfooter__name {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: -0.02em;
}

.mfooter__tag {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  max-width: 28ch;
}

.mfooter__cols {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--spacing-24);
}

.mfooter__label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.mfooter__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-8);
}

.mfooter__list a {
  font-size: var(--text-body-sm);
  color: inherit;
  text-decoration: none;
}

.mfooter__list a:hover {
  text-decoration: underline;
}

.mfooter__legal {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--spacing-8);
  padding-block: var(--spacing-20);
  border-top: 1px solid var(--color-border);
  font-size: var(--text-meta);
  color: var(--color-muted);
}

@media (min-width: 768px) {
  .mfooter__inner {
    grid-template-columns: 1fr 2fr;
  }
}
</style>

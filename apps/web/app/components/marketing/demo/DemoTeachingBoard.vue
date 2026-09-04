<template>
  <ProductFrame title="Teaching Studio">
    <div class="teach-demo">
      <div class="teach-demo__canvas" role="img" aria-label="Road board showing a roundabout with lane arrows">
        <svg viewBox="0 0 400 280" class="teach-demo__svg">
          <rect width="400" height="280" fill="#eef3f0" />
          <ellipse cx="200" cy="140" rx="70" ry="70" fill="#d8dde0" stroke="#1E2C0F" stroke-width="3" />
          <path d="M200 20 L200 70 M200 210 L200 260 M20 140 L70 140 M330 140 L380 140" stroke="#1E2C0F" stroke-width="14" stroke-linecap="round" />
          <path d="M200 70 Q240 100 260 140" stroke="#1E2C0F" stroke-width="3" fill="none" marker-end="url(#arrow)" />
          <circle cx="200" cy="200" r="12" fill="#ff4141" />
          <text x="200" y="250" text-anchor="middle" font-size="12" fill="#1E2C0F">Third exit — lane 2</text>
          <defs>
            <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
              <path d="M0,0 L8,3 L0,6 Z" fill="#1E2C0F" />
            </marker>
          </defs>
        </svg>
      </div>
      <aside class="teach-demo__aside">
        <p class="teach-demo__tag">Shared with Amina</p>
        <p class="teach-demo__title">Roundabout — third exit</p>
        <p class="teach-demo__copy">Lane choice and exit signal saved to the lesson.</p>
      </aside>
    </div>
  </ProductFrame>
</template>

<style scoped>
.teach-demo {
  display: grid;
  gap: var(--spacing-16);
}

@media (min-width: 768px) {
  .teach-demo {
    grid-template-columns: 1.4fr 1fr;
  }
}

.teach-demo__canvas {
  border-radius: var(--radius-small);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.teach-demo__svg {
  display: block;
  width: 100%;
  height: auto;
}

.teach-demo__tag {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.teach-demo__title {
  font-size: var(--text-body-sm);
  margin-top: var(--spacing-8);
}

.teach-demo__copy {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: var(--spacing-8);
}
</style>

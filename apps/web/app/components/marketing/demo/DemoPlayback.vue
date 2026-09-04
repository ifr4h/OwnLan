<template>
  <ProductFrame title="Lesson playback">
    <div class="playback-demo">
      <div class="playback-demo__map" role="img" aria-label="Lesson route map with marked moment">
        <svg viewBox="0 0 360 200" class="playback-demo__svg">
          <rect width="360" height="200" fill="#e8efe9" />
          <path d="M40 160 Q120 40 200 100 T320 60" stroke="#168B55" stroke-width="4" fill="none" stroke-linecap="round" />
          <circle cx="200" cy="100" r="10" fill="#ff4141" stroke="#fff" stroke-width="2" />
          <circle cx="40" cy="160" r="6" fill="#168B55" />
          <circle cx="320" cy="60" r="6" fill="#168B55" />
        </svg>
      </div>
      <div class="playback-demo__timeline">
        <div class="playback-demo__scrub">
          <div class="playback-demo__playhead" style="left: 45%" />
        </div>
        <ul class="playback-demo__moments">
          <li><span>0:00</span> Pickup</li>
          <li class="playback-demo__moments--active"><span>0:42</span> Roundabout — lane choice</li>
          <li><span>1:18</span> Third exit</li>
        </ul>
      </div>
      <p class="playback-demo__skill">Skills: Roundabouts · Developing</p>
    </div>
  </ProductFrame>
</template>

<style scoped>
.playback-demo {
  display: grid;
  gap: var(--spacing-16);
}

.playback-demo__map {
  border-radius: var(--radius-small);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.playback-demo__svg {
  display: block;
  width: 100%;
}

.playback-demo__scrub {
  position: relative;
  height: 6px;
  background: var(--color-border);
  border-radius: 3px;
}

.playback-demo__playhead {
  position: absolute;
  top: -4px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
  transform: translateX(-50%);
}

.playback-demo__moments {
  list-style: none;
  margin: var(--spacing-12) 0 0;
  padding: 0;
  display: grid;
  gap: var(--spacing-8);
  font-size: var(--text-meta);
}

.playback-demo__moments li {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: var(--spacing-8);
}

.playback-demo__moments span {
  font-family: var(--font-martian-mono);
  color: var(--color-muted);
}

.playback-demo__moments--active {
  color: var(--color-ownlane-green);
  font-weight: 500;
}

.playback-demo__skill {
  font-size: var(--text-body-sm);
  padding-top: var(--spacing-8);
  border-top: 1px solid var(--color-border);
}
</style>

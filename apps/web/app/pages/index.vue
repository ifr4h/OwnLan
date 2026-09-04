<template>
  <div>
    <MarketingStructuredData />
    <!-- Hero -->
    <section class="home-hero marketing-section">
      <div class="marketing-shell--wide home-hero__grid">
        <div class="home-hero__copy motion-reveal">
          <p class="marketing-eyebrow">{{ copy.hero.eyebrow }}</p>
          <h1 class="marketing-display">{{ copy.hero.title }}</h1>
          <p class="marketing-lead">{{ copy.hero.lead }}</p>
          <div class="marketing-actions">
            <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary">
              {{ site.cta.primary }}
            </NuxtLink>
            <a :href="site.cta.secondaryTo" class="marketing-btn marketing-btn--ghost">
              {{ site.cta.secondary }}
            </a>
          </div>
        </div>
        <div class="home-hero__visual motion-reveal motion-reveal--delay-1">
          <DemoTodayHero />
        </div>
      </div>
    </section>

    <!-- Independence -->
    <section class="marketing-section marketing-section--tight">
      <div class="marketing-shell home-narrow">
        <h2 class="marketing-h2">{{ copy.independence.title }}</h2>
        <p class="marketing-body">{{ copy.independence.body }}</p>
      </div>
    </section>

    <!-- Working day -->
    <section id="how-it-works" class="marketing-section marketing-section--wash">
      <div class="marketing-shell">
        <h2 class="marketing-h2">{{ copy.workingDay.title }}</h2>
        <p class="marketing-body home-narrow">{{ copy.workingDay.intro }}</p>
        <div class="home-spacer">
          <DemoDayMoments />
        </div>
      </div>
    </section>

    <!-- Diary -->
    <section class="marketing-section">
      <div class="marketing-shell marketing-grid-2">
        <div>
          <h2 class="marketing-h2">{{ copy.diary.title }}</h2>
          <p class="marketing-body">{{ copy.diary.body }}</p>
          <NuxtLink to="/features/diary" class="marketing-link home-link">Diary feature</NuxtLink>
        </div>
        <DemoDiaryDay />
      </div>
    </section>

    <!-- Pupil -->
    <section class="marketing-section marketing-section--paper">
      <div class="marketing-shell marketing-grid-2 marketing-grid-2--reverse">
        <div>
          <h2 class="marketing-h2">{{ copy.pupil.title }}</h2>
          <p class="marketing-body">{{ copy.pupil.body }}</p>
          <NuxtLink to="/features/pupils" class="marketing-link home-link">Pupil records</NuxtLink>
        </div>
        <DemoPupilCard />
      </div>
    </section>

    <!-- Continuity -->
    <section class="marketing-section">
      <div class="marketing-shell">
        <h2 class="marketing-h2">{{ copy.continuity.title }}</h2>
        <p class="marketing-body home-narrow">{{ copy.continuity.body }}</p>
        <div class="home-spacer">
          <DemoContinuityFlow />
        </div>
      </div>
    </section>

    <!-- Teaching -->
    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell marketing-grid-2">
        <DemoTeachingBoard />
        <div>
          <h2 class="marketing-h2">{{ copy.teaching.title }}</h2>
          <p class="marketing-body">{{ copy.teaching.body }}</p>
          <NuxtLink to="/features/teaching" class="marketing-link home-link">Teaching tools</NuxtLink>
        </div>
      </div>
    </section>

    <!-- Learner -->
    <section class="marketing-section">
      <div class="marketing-shell marketing-grid-2">
        <div>
          <h2 class="marketing-h2">{{ copy.learner.title }}</h2>
          <p class="marketing-body">{{ copy.learner.body }}</p>
          <NuxtLink to="/learners" class="marketing-link home-link">For learners</NuxtLink>
        </div>
        <DemoLearnerPhone />
      </div>
    </section>

    <!-- Playback -->
    <section class="marketing-section marketing-section--paper">
      <div class="marketing-shell marketing-grid-2 marketing-grid-2--reverse">
        <div>
          <h2 class="marketing-h2">{{ copy.playback.title }}</h2>
          <p class="marketing-body">{{ copy.playback.body }}</p>
        </div>
        <DemoPlayback />
      </div>
    </section>

    <!-- Progress -->
    <section class="marketing-section">
      <div class="marketing-shell marketing-grid-2">
        <div>
          <h2 class="marketing-h2">{{ copy.progress.title }}</h2>
          <p class="marketing-body">{{ copy.progress.body }}</p>
          <NuxtLink to="/features/progress" class="marketing-link home-link">Progress tracking</NuxtLink>
        </div>
        <DemoProgressTimeline />
      </div>
    </section>

    <!-- Money -->
    <section class="marketing-section marketing-section--wash">
      <div class="marketing-shell marketing-grid-2 marketing-grid-2--reverse">
        <div>
          <h2 class="marketing-h2">{{ copy.money.title }}</h2>
          <p class="marketing-body">{{ copy.money.body }}</p>
          <NuxtLink to="/features/money" class="marketing-link home-link">Money overview</NuxtLink>
        </div>
        <DemoMoneyOverview />
      </div>
    </section>

    <!-- Connection -->
    <section class="marketing-section">
      <div class="marketing-shell">
        <h2 class="marketing-h2">{{ copy.connection.title }}</h2>
        <p class="marketing-body home-narrow">{{ copy.connection.body }}</p>
        <div class="home-spacer">
          <DemoConnectionSplit />
        </div>
      </div>
    </section>

    <!-- Beta CTA -->
    <section class="marketing-section marketing-section--paper home-cta">
      <div class="marketing-shell home-cta__inner">
        <h2 class="marketing-h2">{{ copy.beta.title }}</h2>
        <p class="marketing-body">{{ copy.beta.body }}</p>
        <div class="marketing-actions">
          <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary">
            {{ site.cta.primary }}
          </NuxtLink>
          <NuxtLink to="/pricing" class="marketing-btn marketing-btn--ghost">Pricing</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { homeCopy as copy } from '~/marketing/content/copy/home'
import { marketingSite as site } from '~/marketing/content/site'

definePageMeta({ layout: 'marketing' })

useMarketingSeo({
  title: 'OwnLane — Run your driving school. Keep your independence.',
  description:
    'Diary, pupils, lessons, progress and money for independent UK driving instructors. A proper learner portal for your pupils.',
  path: '/',
})
</script>

<style scoped>
.home-hero__grid {
  display: grid;
  gap: var(--spacing-40);
  align-items: center;
  width: min(100% - var(--spacing-32), var(--marketing-wide));
  margin-inline: auto;
}

@media (min-width: 1024px) {
  .home-hero__grid {
    grid-template-columns: 1fr 1.1fr;
    gap: var(--spacing-48);
  }
}

.home-narrow {
  max-width: 52ch;
}

.home-spacer {
  margin-top: var(--spacing-32);
}

.home-link {
  display: inline-block;
  margin-top: var(--spacing-16);
}

.home-cta__inner {
  max-width: 640px;
}

.marketing-grid-2__visual {
  min-width: 0;
}
</style>

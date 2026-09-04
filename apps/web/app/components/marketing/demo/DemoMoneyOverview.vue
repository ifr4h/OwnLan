<template>
  <ProductFrame title="Money · September">
    <div class="money-demo">
      <p class="money-demo__month">{{ money.month }}</p>
      <div class="money-demo__grid">
        <div class="money-demo__stat">
          <p class="money-demo__val">{{ money.earned }}</p>
          <p class="money-demo__lbl">earned</p>
        </div>
        <div class="money-demo__stat money-demo__stat--warn">
          <p class="money-demo__val">{{ money.outstanding }}</p>
          <p class="money-demo__lbl">outstanding</p>
        </div>
        <div class="money-demo__stat">
          <p class="money-demo__val">{{ money.projected }}</p>
          <p class="money-demo__lbl">projected</p>
        </div>
      </div>
      <div class="money-demo__goal">
        <div class="money-demo__goal-head">
          <span>Goal {{ money.goal }}</span>
          <span>{{ money.shortOfGoal }} short</span>
        </div>
        <div class="money-demo__bar" role="presentation">
          <div class="money-demo__bar-fill" style="width: 67%" />
        </div>
      </div>
      <p class="money-demo__hint">Diary opportunities: {{ money.diaryHours }} free this month</p>
    </div>
  </ProductFrame>
</template>

<script setup lang="ts">
import { marketingMoney as money } from '~/marketing/content/demo-data'
</script>

<style scoped>
.money-demo__month {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-muted);
}

.money-demo__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-12);
  margin-top: var(--spacing-16);
}

.money-demo__stat {
  padding: var(--spacing-12);
  background: var(--color-frost-green);
  border-radius: var(--radius-small);
}

.money-demo__stat--warn {
  background: var(--color-warning-wash);
}

.money-demo__val {
  font-family: var(--font-martian-mono);
  font-size: 20px;
  letter-spacing: -0.02em;
}

.money-demo__lbl {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 4px;
}

.money-demo__goal {
  margin-top: var(--spacing-20);
}

.money-demo__goal-head {
  display: flex;
  justify-content: space-between;
  font-size: var(--text-meta);
  margin-bottom: var(--spacing-8);
}

.money-demo__bar {
  height: 8px;
  background: var(--color-border);
  border-radius: 4px;
  overflow: hidden;
}

.money-demo__bar-fill {
  height: 100%;
  background: var(--color-ownlane-green);
  border-radius: 4px;
}

.money-demo__hint {
  margin-top: var(--spacing-16);
  font-size: var(--text-body-sm);
  color: rgba(17, 17, 24, 0.75);
}
</style>

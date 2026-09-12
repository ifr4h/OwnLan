# OwnLane Phase 2 — Smart Calendar

## Purpose

Phase 2 should make the OwnLane calendar feel less like a place where appointments are stored and more like a system that understands an instructor's day.

The calendar should continuously connect:

- time
- pupils
- travel
- lesson history
- packages and credits
- payments
- test dates
- booking habits
- waiting pupils
- cancellations
- future bookings

The goal is not to add more admin.

The goal is to make OwnLane useful from information the instructor already creates simply by running their business.

> OwnLane should learn from operations rather than asking instructors to describe their operations.

A user who mainly uses the diary and payments should still experience a genuinely intelligent product.

---

## Core product rule

The calendar should constantly answer:

> What do I need to notice here?

rather than only:

> What is booked?

Do not add fields simply to make downstream screens richer.

Before adding any instructor input, ask:

1. Do we already know this?
2. Can it be derived reliably?
3. Can it be inferred from existing history?
4. Was it already entered elsewhere?

If yes, derive it.

---

# 1. Smart gaps

Blank calendar space should remain visually calm.

Only genuinely useful gaps should become interactive.

Example:

```text
2h free
3 pupils could fit here
```

On selection:

```text
Best fits

Sarah
Usually books Tuesdays
1h lesson
12 min travel

Jake
Test in 11 days
No lesson booked this week
1h lesson
8 min travel

Aisha
Waiting for a lesson this week
2h lesson
15 min travel
```

Ranking should use deterministic, explainable information such as:

- instructor availability
- pupil availability where known
- historical booking pattern
- required duration
- pickup location
- travel feasibility
- waiting-list status
- test proximity
- whether another lesson is already booked
- gap length

Do not show every pupil who technically fits.

Show the best few and explain why.

---

# 2. Schedule-change preview

When an instructor moves a lesson, OwnLane should show the effect before confirmation.

Example:

```text
Moving Jake to 14:00

Travel from Sarah
12 min → 24 min

Creates
1h 30m free at 10:00

2 pupils could fit that gap

[Move lesson]
```

This should use real schedule and travel data.

Do not manufacture optimisation claims.

---

# 3. Travel-chain awareness

OwnLane should understand the sequence of the whole working day, not only isolated travel times.

Example:

```text
09:00 Sarah
11 min travel

10:30 Jake
18 min travel

12:00 Aisha
```

Useful observations:

```text
Tight turnaround

Jake → Aisha

12 min available
Estimated journey 17 min
```

Or:

```text
Long travel jump
24 min to next pupil
```

Where reliable and genuinely useful, OwnLane may suggest a better sequence:

```text
Swapping these two lessons could reduce travel by about 18 min
```

Never reschedule automatically.

The instructor remains in control.

---

# 4. Smart day reshuffle

Phase 2 may provide optional schedule-improvement suggestions.

Example:

```text
Your afternoon could involve less travel

Current
13:00 Sarah
15:30 Jake
18:00 Aisha

Possible
13:00 Sarah
14:30 Jake
16:00 Aisha

Approx. 38 min less travel
```

Only suggest arrangements that are compatible with known constraints.

Do not imply learner availability unless it is actually known.

---

# 5. Test-aware scheduling

Use existing test dates directly inside calendar context.

Examples:

```text
Jake
Test in 11 days
2 lessons booked before test
```

Week or month context may show:

```text
3 pupils have tests this month
```

On selection:

```text
Jake · 16 Sep
2 lessons before test

Sarah · 23 Sep
No lesson booked in test week

Aisha · 28 Sep
3 lessons booked
```

This is scheduling awareness only.

Do not:

- predict readiness
- predict pass probability
- tell instructors how many lessons a pupil needs
- judge whether a pupil is ready

---

# 6. Credit-aware calendar

Connect packages and credits directly to lessons.

Examples:

```text
2h lesson
Covered by credit

4h left after this lesson
```

Or:

```text
10-hour block
7h used · 3h remaining
```

When the remaining credit has a clear practical meaning:

```text
2h left after today
Enough for one usual 2h lesson
```

Only use "usual" where there is sufficient evidence.

Do not conflate:

- payment received
- package purchased
- credit allocation
- completed lesson
- scheduled lesson
- accounting income

Use the existing authoritative finance domain.

---

# 7. Future-booking coverage

Connect credit balances with future booked hours.

Examples:

```text
3h remaining after today
Your next 3 booked hours are covered
```

Or:

```text
2h remaining
3h currently booked

1 upcoming hour is not covered by credit
```

Keep the tone factual and neutral.

---

# 8. Calendar learns booking habits

Do not make instructors configure every preference manually.

From sufficient history, OwnLane can learn:

- usual weekday
- approximate usual time
- usual duration
- usual pickup
- typical booking frequency

Examples:

```text
Usually Sundays around 10:00
```

```text
Usually has 1-hour lessons
```

```text
Usually weekly
```

Use conservative confidence thresholds.

Do not infer a stable pattern from a couple of coincidental bookings.

---

# 9. Unusual-event signals

The calendar should quietly surface deviations from normal behaviour.

Examples:

```text
Different pickup today
```

```text
First lesson in 6 weeks
```

```text
First 2h lesson
Usually 1h
```

```text
Moved twice
```

```text
No next lesson booked
Usually weekly
```

```text
Test moved to next month
```

Only show unusual information when it is likely to help the instructor.

---

# 10. One-click usual booking

Book next should use what OwnLane already knows.

Example:

```text
Book Jake again

Suggested

Sunday 13 September
10:00–11:00
4 Church Street

[Book]
```

Prefill from trustworthy context:

- pupil
- usual or preferred duration
- usual pickup
- lesson type where reliable
- recent booking cadence
- current availability

Then run the normal authoritative conflict and travel validation.

Do not open an empty booking form.

---

# 11. Day-start awareness

Today should surface the few things worth noticing.

Example:

```text
6 lessons
7.5 teaching hours
1 tight journey
2 pupils have no next lesson
£76 still to collect
```

Do not turn the main calendar into a dashboard.

Prefer placing relevant context on the actual appointments.

Examples:

```text
10:00 Jake
No next lesson booked
```

```text
13:00 Sarah
8 min travel
```

```text
17:00 Aisha
£38 due
```

---

# 12. What changed since last view

Once learner requests and rescheduling are in use, OwnLane can show meaningful changes.

Example:

```text
Since yesterday

Sarah moved 14:00 → 16:00
Jake cancelled Friday
2h gap opened Thursday
```

Only include changes that genuinely affect the instructor's schedule.

---

# 13. Cancellation recovery

When a cancellation creates usable capacity, OwnLane should immediately connect that gap with demand.

Example:

```text
2h opened Thursday at 14:00

Best matches

Sarah
Asked for a lesson this week

Jake
Usually books Thursday afternoons

Hannah
Waiting for a lesson
```

Then allow:

```text
[Book Sarah here]
```

This should connect with the existing Empty Seat Engine / Gap Matching logic rather than creating a duplicate system.

---

# 14. Waiting list inside the calendar

Do not make the waiting list a separate dead database.

Useful free windows may show:

```text
90 min free

2 waiting pupils fit
```

Tap to see ranked matches.

The calendar becomes where demand and availability meet.

---

# 15. Last booked lesson awareness

If a pupil has no future booking after this lesson, show it subtly.

Example:

```text
LAST BOOKED
```

On opening:

```text
This is Sarah's last booked lesson

Usually weekly
Nothing booked after 18 Sep

[Book next]
```

Do not nag.

This is context plus a convenient action.

---

# 16. Neutral schedule observations

Avoid optimisation scores.

Do not say:

```text
Your week is 83% optimised
```

Prefer concrete observations:

```text
Thursday has 2 long gaps

Friday has 3 tight journeys

Tuesday is mostly continuous
```

More lessons are not automatically better.

Free time is not automatically wasted time.

---

# 17. Understand intentional free time

OwnLane must distinguish between:

- working availability
- breaks
- time off
- travel buffer
- genuinely free teaching capacity
- intentionally unbooked time

Do not turn every blank calendar region into a sales prompt.

Only surface pupil matches where the time is genuinely usable.

---

# 18. Smart click on empty time

Clicking an empty region should be useful immediately.

Example:

```text
15:20 selected

Available until 17:30

Could fit:
1 × 2h lesson
2 × 1h lessons

3 pupils match this time

[Book pupil]
[Block time]
```

If the instructor chooses Book pupil, show suitable pupils rather than an empty booking form.

---

# 19. Smart gap between two lessons

OwnLane should consider the appointments surrounding a gap.

Example:

```text
12:00–14:00 free

Previous
Sarah · Wolverton

Next
Jake · Newport Pagnell

4 pupils could fit this gap
```

Where location logic is reliable, rank pupils partly by travel fit.

Avoid pretending there is an exact "best area" unless supported by the routing data.

---

# 20. Book another pupil nearby

From an existing lesson:

```text
Book another pupil nearby
```

OwnLane may identify pupils whose pickup locations and availability fit before or after the selected lesson.

This should be a contextual planning tool, not a permanent primary action.

---

# 21. Day route view

Provide an optional daily route/context view.

Example:

```text
Home
↓
Sarah
↓
Jake
↓
Aisha
↓
Home
```

Useful derived facts may include:

```text
Today
42 miles
1h 18m estimated travel
```

Only show distance/time when the data is reliable.

This may be better suited to Diary Overview than the default calendar grid.

---

# 22. Pupil-history navigation from the calendar

When viewing a pupil's past lesson, allow lightweight navigation through that pupil's lesson history.

Example:

```text
← 30 Aug    6 Sep    13 Sep →
```

These controls should move through Jake's lessons, not simply adjacent calendar days.

This lets the instructor move through a pupil's teaching journey without leaving the Diary.

---

# 23. Recurrence detection

Do not force instructors to configure recurrence upfront.

If a clear repeated pattern develops:

```text
Jake has had 5 Sunday 10:00 lessons

Make this recurring?
```

Then one click can create an appropriate recurrence pattern.

Only prompt after strong evidence.

Do not repeatedly nag if declined.

---

# 24. Missing-pattern detection

If a clear cadence exists and one expected period is missing:

```text
No lesson next Sunday
Usually weekly
```

This should remain subtle.

Never present an inferred pattern as a formal commitment.

---

# 25. Package + recurrence awareness

If recurring future bookings continue beyond available credit:

```text
Current credit covers the next 2 lessons
Later recurring lessons are not covered yet
```

Keep the language factual.

Do not turn it into an upsell prompt.

---

# 26. End-of-block awareness

Examples:

```text
Last lesson covered by current block
```

```text
Next booked lesson is not covered by current credit
```

This is more useful than exposing only the numeric balance.

---

# 27. State-aware calendar actions

Actions should reflect what is happening now.

Requested:

```text
Sarah requested 14:00

Fits your diary
12 min from previous lesson

[Approve]
[Choose another time]
```

Upcoming:

```text
[Move]
[Book next]
```

Lesson ending / recently ended:

```text
[Complete lesson]
[Book next]
```

Past unresolved:

```text
This lesson ended 2h ago

[Complete]
[Mark no-show]
```

Completed:

```text
[View recap]
[Book next]
```

Do not show irrelevant actions merely because they are available somewhere in the domain.

---

# Signature Phase 2 interaction

The strongest expression of the Smart Calendar should be something like:

```text
You have 1h 45m free between Sarah and Jake.

3 pupils could fit.

Best match
Aisha

Why:
• usually available Thursday afternoons
• 11 min from Sarah
• 9 min to Jake
• no lesson booked this week

[Book Aisha here]
```

This is the target experience.

It combines:

- diary availability
- pupil history
- geography
- travel
- continuity
- future bookings
- demand

without asking the instructor to enter anything new.

---

# Intelligence rules

All Phase 2 calendar intelligence must be:

- deterministic
- explainable
- based on real OwnLane data
- conservative where confidence is limited
- tenant scoped
- permission aware
- optional rather than controlling

Avoid:

- opaque AI recommendations
- pass predictions
- readiness predictions
- productivity scores
- automatic rescheduling
- pressure to fill all free time
- invented learner availability
- invented booking preferences
- fake travel precision

When evidence is weak, omit the insight.

---

# UX rules

The smart calendar must become simpler as intelligence increases.

Do not build:

- an "AI Insights" sidebar
- giant warning cards
- endless status chips
- analytics clutter over the grid
- badges on every appointment
- tooltip-dependent functionality
- constant prompts

Prefer:

- one useful sentence
- contextual actions
- tiny status markers
- progressive disclosure
- ranked suggestions
- natural placement of information

The desired feeling is:

> OwnLane already noticed that.

Not:

> OwnLane has a lot of features.

---

# Definition of done

Phase 2 succeeds when an instructor can run their normal day with little or no additional admin and the calendar still:

- notices meaningful gaps
- understands travel between lessons
- connects cancellations with waiting pupils
- recognises useful booking patterns
- makes rebooking almost one tap
- understands package/credit consequences
- surfaces test-date scheduling context
- notices unusual pickups or long breaks
- identifies pupils without another lesson booked
- provides useful context when empty time is selected
- explains why a pupil is a good fit for a gap
- changes actions based on lesson state
- helps the instructor make better schedule decisions without taking control away from them

The product should feel smart because it understands the instructor's existing data, not because the instructor has been forced to maintain more data.

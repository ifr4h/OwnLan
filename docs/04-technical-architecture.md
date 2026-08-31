# Technical Architecture

## Philosophy

Start with a **modular monolith**. Do not begin with microservices,
Kubernetes, Kafka or service-mesh complexity.

## Initial stack

-   Web: Nuxt + Vue + TypeScript
-   Backend: Yii2 API initially if it maximises founder speed
-   Database: PostgreSQL
-   Cache/jobs: Redis initially
-   Files: S3-compatible object storage
-   API: REST + OpenAPI
-   CI/CD: GitHub Actions or equivalent
-   Monitoring: errors + logs + metrics
-   Cloud: managed AWS-style infrastructure

Laravel remains possible later; framework migration is not
product-market fit.

## One backend, multiple clients

Shared core/API for: - instructor web - learner web - instructor native
later - learner native later - marketplace - internal admin

## Multi-tenancy

Do not model `User -> Pupils`.

Model:

`User -> Membership -> Organisation -> Instructors / Learners / Lessons`

Independent instructor = organisation with one instructor.

Every business record should be tenant-scoped and every request should
resolve identity, active organisation and permission.

## Modules

-   Auth/Identity
-   Organisations
-   Membership/RBAC
-   Instructors
-   Learners
-   Locations
-   Lessons
-   Availability/Scheduling
-   Progress
-   Payments
-   Packages/Balances
-   Expenses/Finance
-   CRM
-   Notifications
-   Marketplace
-   Subscriptions
-   Files
-   Audit

## Scale path

1.  stateless app/API
2.  managed PostgreSQL
3.  Redis
4.  workers
5.  object storage/CDN
6.  load balancing/horizontal scale
7.  specialised services/read replicas only when measured bottlenecks
    require them

## Offline

Native instructor app later should cache today's diary/pupil essentials
and queue lesson completion, notes and progress safely.

## Application events

Examples: - LessonBooked - LessonCompleted - LessonCancelled -
PaymentRecorded - PackagePurchased - ProgressUpdated - ExpenseRecorded

Initially internal monolith events/background jobs.

## Provider abstraction

Keep Stripe/maps/messaging/accounting providers behind adapters where
lock-in would be expensive.

## Safety

Design instructor interaction for use while safely parked, not while
driving.

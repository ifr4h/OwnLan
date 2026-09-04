# Accounts 2.0

Instructor business overview connecting money, bookings, capacity and goals.

## Navigation

Accounts sub-sections: Overview, Payments, Expenses, Reports, Vehicles, Mileage.

## Metric definitions

| Label | Meaning |
|-------|---------|
| **Teaching income** | Completed lesson value in period. Package lessons valued at org hourly rate × duration. Charged no-shows included. Waived excluded. |
| **Payments received** | `payments` where `counts_as_income = true` (cash timing, includes package purchases). |
| **Still to collect** | Sum of outstanding `lesson_charges`. |
| **Booked before period end** | Future `scheduled` lessons before range end at effective lesson price. |
| **Projected from current bookings** | Teaching income + booked value. Not a forecast guarantee. |
| **Average teaching value** | Teaching income ÷ completed teaching minutes in period. |
| **Estimated additional teaching** | Goal gap ÷ average hourly teaching value. |
| **Usable diary capacity** | Future gaps within working hours, each ≥ 60 minutes. |

## Effective lesson value

See `TeachingValueResolver`:

1. Waived → £0
2. `price_pence > 0` → stored price
3. Else → `default_hourly_rate_pence × duration_minutes / 60` (integer pence)

## Capacity

`TeachingCapacityService` respects:

- Configured working days and hours
- Scheduled lessons (not cancelled)
- Minimum 60-minute gaps (same as gap matching)
- Future portion of today only

Pupil opportunities use `GapMatchingService` with distinct pupil and match counts reported separately.

## Receipts

Stored under `@runtime/storage/receipts/{org_id}/{expense_id}/`. Served only via authenticated API.

## Deferred

- Receipt OCR, bank feeds, tax estimates, HMRC submission, automatic mileage from lesson GPS.

# PolicyFlow — Fault-Tolerant Insurance Issuance & Reconciliation Engine

PolicyFlow is a Laravel 12 application that simulates a modern insurance operations control center for intake, premium validation, issuer submission, retries, and reconciliation. It is designed to satisfy the assignment requirements for a fault-tolerant policy issuance workflow and a polished control dashboard.

## Main features

- Policy intake with business validation and premium locking.
- Duplicate prevention using agent + client request idempotency and DB uniqueness.
- State-based issuance workflow with audit history.
- Mock insurer simulator with development-only scenario controls.
- Safe reconciliation after timeout and callback deduplication.
- Dashboard pages for overview, request list, and request detail views, including CSV export, issuance submission, and provider reconciliation.
- Automated tests covering premium rules, invalid inputs, and reliability scenarios.

## Technology stack

- PHP 8.2
- Laravel 12
- SQLite for local development and testing
- Laravel Blade + Tailwind CSS + Alpine.js
- Vite for asset bundling

## Architecture and data flow

- `app/Http/Requests` validates inbound API payloads.
- `app/Services/PremiumCalculator.php` owns the pricing rules and decimal-safe math.
- `app/Services/PolicyRequestIntakeService.php` creates or retrieves an idempotent request.
- `app/Services/PolicyWorkflow.php` enforces valid transitions.
- `app/Services/MockInsurerService.php` simulates insurer responses and callback processing.
- `database/migrations` create the normalized policy data model.
- `routes/api.php` exposes the required API interfaces.
- `routes/web.php` powers the dashboard UI.

## Database schema and constraints

The project uses four primary tables:

- `policy_requests`
  - unique public request ID
  - unique composite key on `agent_id + client_request_id`
  - immutable premium snapshot
  - provider reference and issued policy number
  - workflow status and attempt count
- `issuance_attempts`
  - provider attempt records and payloads
- `provider_events`
  - deduplicated insurer callbacks and provider notifications
- `policy_state_history`
  - chronological transition log with source and reason

## Duplicate prevention strategy

Duplicate intake is protected in several layers:

- Application-level check on `agent_id + client_request_id`
- Transaction + row lock before insert
- Database unique constraint on the composite key
- Reuse of the original premium snapshot rather than recalculation
- Callback deduplication using an event id

## Premium calculation rules

Formula:

`premium = daily_base_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier`

Base rates:

- BASIC = ₹12
- PLUS = ₹20
- PREMIUM = ₹30

Age multiplier by trip start age:

- 0–17: 1.10
- 18–60: 1.00
- 61–70: 1.50
- 71+: 2.00

Destination multiplier:

- DOMESTIC = 1.00
- INTERNATIONAL = 1.80

Coverage multiplier:

- ₹100,000 = 1.00
- ₹300,000 = 1.25
- ₹500,000 = 1.50

Travel days:

- `trip_end - trip_start + 1`, minimum one day

Rounding:

- Decimal-safe arithmetic with `HALF_UP` rounding to two decimal places.

Example:

- 5-day international PLUS trip
- age multiplier 1.00
- coverage multiplier 1.25
- result = ₹225.00

## State transitions

The workflow is enforced centrally by `PolicyWorkflow`:

- RECEIVED -> VALIDATED
- VALIDATED -> SUBMITTED or FAILED
- SUBMITTED -> PENDING_CONFIRMATION, FAILED, NEEDS_RECONCILIATION
- PENDING_CONFIRMATION -> ISSUED, FAILED, NEEDS_RECONCILIATION
- FAILED -> SUBMITTED, VALIDATED
- NEEDS_RECONCILIATION -> ISSUED, FAILED, SUBMITTED
- ISSUED is terminal

Every transition is recorded in `policy_state_history`.

## Mock insurer and supported scenarios

The project contains a local deterministic mock insurer with these scenarios:

- `success`
- `temporary_failure`
- `timeout_after_issue`
- `delayed_callback`
- `duplicate_callback`

These are configurable through `.env` and `config/policyflow.php` and disabled in production.

The dashboard uses file-backed sessions by default so CSRF tokens remain valid across requests. Scenario selections made through the development API are persisted in the configured cache store for subsequent submission requests.

## Prerequisites

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite extension enabled for PHP

## Installation and setup

1. Install PHP dependencies:

```bash
composer install
```

2. Copy environment variables:

```bash
cp .env.example .env
php artisan key:generate
```

3. Run migrations:

```bash
php artisan migrate
```

4. Install frontend dependencies and compile assets:

```bash
npm install
npm run build
```

5. Start the application:

```bash
php artisan serve
```

Open:

- http://localhost:8000/dashboard
- http://localhost:8000/api/health

## Run automated tests

```bash
php artisan test
```

The PHPUnit config uses in-memory SQLite for isolated test runs.

## API examples

### Create policy request

```bash
curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
```

### Repeat same request

```bash
curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
```

### Retrieve policy detail

```bash
curl http://localhost:8000/api/policy-requests/<public_request_id>
```

### List policy requests with pagination and status filtering

```bash
curl "http://localhost:8000/api/policy-requests?page=1&status=VALIDATED"
```

### Submit request to mock insurer

```bash
curl -X POST http://localhost:8000/api/policy-requests/<public_request_id>/submit
```

### Trigger a mock timeout-after-issue scenario

```bash
curl -X POST http://localhost:8000/api/mock-insurer/scenario \
  -H "Content-Type: application/json" \
  -d '{"scenario":"timeout_after_issue"}'
```

### Reconcile a timed-out provider issue

```bash
curl -X POST http://localhost:8000/api/mock-insurer/reconcile \
  -H "Content-Type: application/json" \
  -d '{"provider_reference":"REF-..."}'
```

### Send a callback

```bash
curl -X POST http://localhost:8000/api/mock-insurer/callback \
  -H "Content-Type: application/json" \
  -d '{
    "provider_reference": "REF-123",
    "event_id": "EV-123",
    "policy_number": "POL-123"
  }'
```

### Validation failure example

```bash
curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0002",
    "agent_id": "AGENT-17",
    "customer_name": "Bad Input",
    "date_of_birth": "2099-01-01",
    "destination": "EUROPE",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-09",
    "plan_code": "GOLD",
    "sum_insured": 200000
  }'
```

## Known limitations

- This project uses a deterministic local mock insurer rather than a real provider integration.
- The dashboard is intentionally focused on operations workflow and not a regulated production portal.
- The solution is designed for assignment / interview explainability, not real-world insurance compliance.

## Repository note

This project was prepared in the local workspace. It has not been pushed to GitHub, and no candidate ID or actual repository URL is claimed.

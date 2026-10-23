# AI Usage

This project was developed with support from the Copilot coding workflow and local Laravel tooling. The intent was to accelerate implementation while keeping the business logic, validations, and test results independently verified by the project itself.

## AI tools used

- GitHub Copilot / Copilot SDK in VS Code
- Laravel artisan commands for generating and validating application behavior
- PHPUnit for automated verification
- Vite for front-end build validation

## Concrete ways AI assisted development

1. Generated the initial Laravel project structure and route scaffolding so the project could be built quickly on top of a valid framework foundation.
2. Drafted service classes for premium calculation, idempotent intake, policy workflow, and mock insurer operations based on the assignment requirements.
3. Produced dashboard Blade templates and styling to match the required operations-control look and feel while keeping backend logic separate.

## Materially incorrect or incomplete suggestions

No materially incorrect implementation changes were accepted without independent validation. A few early assumptions needed correction, including:

- Carbon's `age()` method was not available in the installed version; the solution was adjusted to use `diffInYears()`.
- Initial route signatures for the API detail and submit endpoints required correction so they could resolve by public request ID instead of only by Eloquent model binding.

## What was independently tested or verified

- Full feature suite for the assignment logic via `php artisan test tests/Feature/PolicyFlowApiTest.php`
- Premium example for the specified 5-day international PLUS trip
- Retry and duplicate-intake behavior
- Timeout-after-issue reconciliation flow
- Callback deduplication and state transition enforcement
- Front-end build via `npm run build`

## Remaining limitations

- The mock insurer is deliberately local and deterministic; it is not a real insurance backend or external gateway.
- The UI is a functional operations dashboard, not a production-grade insurance portal with regulatory workflows.
- This assignment does not claim actual GitHub submission or remote repository creation without explicit user action.

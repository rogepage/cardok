<!--
Sync Impact Report:
- Version change: [TEMPLATE] -> 1.0.0 (Initial Ratification)
- Core Principles Defined:
  1. Clean Architecture & Domain Isolation (NON-NEGOTIABLE)
  2. Exact Financial Precision & Monetary Invariants (NON-NEGOTIABLE)
  3. Resilient Integration & First-Success-Wins
  4. Strict Payment Simulation Boundaries
  5. Temporal Determinism via Clock Injection
  6. Pragmatic Observability & Privacy by Design (LGPD)
  7. Automated Test Discipline & Regression Defense
- Added Sections: Technical Constraints, Development Workflow & Quality Gates, Governance
- Removed Sections: None (Template placeholders instantiated)
- Templates Status:
  - .specify/templates/plan-template.md: ✅ aligned (gates mapped to constitution checks)
  - .specify/templates/spec-template.md: ✅ aligned (scope and requirements boundaries defined)
  - .specify/templates/tasks-template.md: ✅ aligned (tasks reflect test-first and financial discipline)
- Deferred Items / Follow-up: None.
-->

# Cardok Constitution

## Core Principles

### I. Clean Architecture & Domain Isolation (NON-NEGOTIABLE)
The core business domain (`app/Domain`) MUST remain 100% pure, framework-agnostic, and self-contained.
- Domain entities, Value Objects, and Domain Services MUST NOT import or depend on framework classes (e.g., `Illuminate\*`), database drivers, ORM models, or transport layer components (HTTP/SOAP).
- All infrastructure adapters, controllers, middleware, and third-party integrations MUST reside strictly in `app/Infrastructure`, `app/Application`, or `app/Http`.
- Dependency inversion MUST be preserved: application and domain layers define contracts/interfaces; infrastructure provides implementations.

### II. Exact Financial Precision & Monetary Invariants (NON-NEGOTIABLE)
Financial calculations MUST operate with absolute mathematical determinism.
- All monetary amounts MUST be represented and calculated using the `Money` Value Object internally stored in integer cents (`int`).
- Primitives of type `float` MUST NEVER be used to represent monetary values. In mathematical formulas requiring compounding or exponential factors (such as the Price amortization table), floating-point operations MUST be strictly isolated to the dimensionless analytical coefficient and rounded directly to integer cents once via `HALF_UP`.
- Rounding MUST follow the banking standard `HALF_UP` (`HalfUpRounder`), ensuring that interest on overdue IPVA (0.33%/day capped at 20%), overdue MULTA (1%/day), and PIX discount (5%) resolve to exact, auditable cents without cumulative drift.

### III. Resilient Integration & First-Success-Wins
External integrations with legacy systems (REST and SOAP) MUST be shielded by an Anti-Corruption Layer.
- External payloads MUST be mapped immediately into the unified `CanonicalDebt` model before entering application use cases.
- Integrations MUST implement exponential retry with random jitter for transient errors (HTTP 5xx, timeouts, connection refused) up to a strict attempt limit.
- Fallback between providers MUST follow the **First-Success-Wins** model: the primary provider is consulted first; only when its attempts are exhausted does failover occur to the secondary provider. No concurrent queries, merge, or quorum reconciliation across providers is permitted, preventing accidental duplicate billing.
- Valid responses indicating zero debts (HTTP 200 with empty list or `<debts/>`) MUST NOT trigger fallback.

### IV. Strict Payment Simulation Boundaries
Cardok is a financial and debt consultation platform with payment simulation capabilities.
- The system MUST calculate and present payment options (TOTAL and individual debts via PIX with 5% discount, and Credit Card installments from 1x to 12x via Price Table).
- The system MUST NOT execute real financial charges, connect to real payment acquirers, initiate real transactions, generate live PIX QR codes, or persist transaction orders in a database, strictly preserving the challenge scope.

### V. Temporal Determinism via Clock Injection
All business rules and tests that depend on date or time calculations MUST be temporally deterministic.
- Code calculating elapsed overdue days, interest accumulation, or maturity MUST inject and consume `ClockInterface` (`FixedClock`).
- System or operating system time (`now()`, `Carbon::now()`, `date()`) MUST NOT be called directly within domain policies or services.
- The production baseline date is pinned to `2024-05-10T00:00:00Z`, ensuring immutable test reproducibility today and in the future.

### VI. Pragmatic Observability & Privacy by Design (LGPD)
Every operation passing through the platform MUST be auditable, correlated, and compliant with privacy standards.
- Every incoming HTTP request MUST be assigned a unique `Request-ID` (UUID v4), injected into logging contexts and echoed in response headers (`X-Request-ID`).
- Logs MUST be emitted in structured JSON format, detailing events, durations (`duration_ms`), attempts, fallbacks, and execution status.
- Sensitive data MUST be sanitized: vehicle license plates MUST be masked (e.g., `ABC****`) in all log outputs and traces to comply with LGPD principles.

### VII. Automated Test Discipline & Regression Defense
Code quality and technical correctness MUST be continuously guarded by automated tests.
- Every financial calculation, domain rule, resilience policy, and API contract MUST have corresponding automated unit and integration tests.
- Zero test failure tolerance: the test suite (`php artisan test`) MUST pass with 100% success before any branch merge or release.
- Regressions on previously fixed P0/P1 issues (such as `invalid_plate` 400 contract, unknown payload rejection, and Price installment values) are strictly unacceptable.

---

## Technical Constraints

- **Language & Runtime:** PHP 8.2+ with strict typing (`declare(strict_types=1)` encouraged, complete argument and return type annotations).
- **Architecture Style:** Hexagonal / Modular Monolith (Clean Architecture) with isolated mock providers.
- **Payload Strictness:** APIs MUST reject unexpected input fields outside the published contract with `HTTP 400 Bad Request`.
- **Infrastructure:** Docker Compose multi-container environment (`cardok-monolith`, `cardok-provider-rest`, `cardok-provider-soap`, `cardok-payment-provider`) with configured health checks and isolated network.
- **Security Baseline:** Mandatory OWASP security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection`, `Referrer-Policy`).

---

## Development Workflow & Quality Gates

1. **Spec-Driven Process:** Features, architectural refactorings, or modifications follow the SDD workflow (`specify` $\to$ `plan` $\to$ `tasks` $\to$ `implement`).
2. **Pre-Implementation Verification:** All requirements, edge cases, and formulas must be validated against canonical specifications before writing production code.
3. **Quality Gates:**
   - Gate 1: Architecture Check (Does changes violate Domain Isolation or introduce float for money?).
   - Gate 2: Test Suite Verification (`php artisan test` passes 100% across all services).
   - Gate 3: Docker Orchestration Validation (`docker compose config` valid, containers healthy).

---

## Governance

- **Authority:** This Constitution is the primary architectural and engineering governance document for the Cardok project. It supersedes informal agreements and undocumented patterns.
- **Amendments:** Any modification to this Constitution requires formal documentation, semantic version bump, and impact analysis on existing templates and tests.
  - **MAJOR (X.0.0):** Incompatible governance shifts, removal of core principles, or architectural restructuring.
  - **MINOR (1.X.0):** Addition of new principles, new quality gates, or expanded architectural policies.
  - **PATCH (1.0.X):** Clarifications, typo corrections, or non-semantic refinements.
- **Review:** All pull requests and feature plans must verify compliance against the principles defined herein.

**Version**: 1.0.0 | **Ratified**: 2026-09-28 | **Last Amended**: 2026-09-28

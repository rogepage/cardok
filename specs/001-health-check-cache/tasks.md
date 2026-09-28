# Tasks: Health Check Integration Cache

**Input**: Design documents from `/specs/001-health-check-cache/`
**Prerequisites**: [plan.md](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/plan.md) (required), [spec.md](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/spec.md) (required), [research.md](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/research.md), [data-model.md](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/data-model.md), [contracts/](file:///Users/roger/Desktop/docker/cardok/specs/001-health-check-cache/contracts)
**Tests**: Automated feature tests covering cache hit, expiration, bypass, and degraded states.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Exact file paths in all descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verification and preparation of testing environment and cache configuration

- [X] T001 Verify cache driver and configuration in monolith/config/cache.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Test scaffolding and fixture setup for health check cache verification

**⚠️ CRITICAL**: Test base must be established before user story tests and implementation begin.

- [X] T002 Create test scaffolding with Cache::flush and test clock resets in monolith/tests/Feature/HealthCheckIntegrationCacheTest.php

**Checkpoint**: Foundational test harness ready. User story implementation can begin.

---

## Phase 3: User Story 1 - Fast Repeated Health Checks with Cache Hit (Priority: P1) 🎯 MVP

**Goal**: Store health check results in cache with 5s TTL and return cached results in <15ms on subsequent calls with `X-Cache: HIT` (or `X-Cache: MISS` on first call) without external HTTP calls.

**Independent Test**: Request endpoint twice within 2 seconds; first returns `X-Cache: MISS`, second returns `X-Cache: HIT` with identical data and status without making external HTTP requests.

### Tests for User Story 1
- [X] T003 [US1] Implement cache hit & X-Cache header integration test in monolith/tests/Feature/HealthCheckIntegrationCacheTest.php

### Implementation for User Story 1
- [X] T004 [US1] Implement 5-second cache lookup, response serving, and X-Cache header injection in monolith/routes/api.php

**Checkpoint**: User Story 1 functional and verified by tests (MVP complete).

---

## Phase 4: User Story 2 - Cache Expiry and Fresh Diagnostic Re-evaluation & Bypass (Priority: P2)

**Goal**: Expire cached diagnostic results after 5 seconds and support immediate bypass via `Cache-Control: no-cache` header.

**Independent Test**: Advance time past 5 seconds (6s) or send `Cache-Control: no-cache`; next request triggers actual HTTP calls, returns `X-Cache: MISS`, and refreshes cache.

### Tests for User Story 2
- [X] T005 [US2] Implement cache expiry and Cache-Control: no-cache bypass tests in monolith/tests/Feature/HealthCheckIntegrationCacheTest.php

### Implementation for User Story 2
- [X] T006 [US2] Implement Cache-Control: no-cache bypass logic, TTL refresh (integer 5), and X-Cache: MISS in monolith/routes/api.php

**Checkpoint**: Cache expires accurately after 5s and reflects updated health state with bypass support.

---

## Phase 5: User Story 3 - Degraded State Reporting Cached Consistently (Priority: P2)

**Goal**: Ensure degraded state (HTTP 503) is also cached for 5s to prevent thundering herds on failing external providers.

**Independent Test**: Simulate failing external provider; verify first call returns 503 degraded and immediate second call also returns 503 degraded from cache without repeating external probes.

### Tests for User Story 3
- [X] T007 [US3] Implement degraded state caching test in monolith/tests/Feature/HealthCheckIntegrationCacheTest.php

### Implementation for User Story 3
- [X] T008 [US3] Store both response payload and HTTP status code (200 vs 503) inside cached payload with error fallback in monolith/routes/api.php

**Checkpoint**: All three user stories functional and covered.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Full regression suite validation and documentation

- [X] T009 Run complete test suite across monolith to verify zero regressions with docker compose exec monolith php artisan test
- [X] T010 [P] Update documentation in docs/spec-driven-development.md with completed feature 001 reference

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (Phase 1)**: No dependencies - executes first
- **Foundational (Phase 2)**: Depends on Setup (T001) - blocks all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (T002)
- **User Story 2 (Phase 4)**: Depends on User Story 1 completion
- **User Story 3 (Phase 5)**: Depends on User Story 1 and 2 completion
- **Polish (Phase 6)**: Depends on all user stories complete

### Within Each User Story
- Test task written and verified failing before implementation task
- T003 (Test US1) → T004 (Impl US1)
- T005 (Test US2) → T006 (Impl US2)
- T007 (Test US3) → T008 (Impl US3)

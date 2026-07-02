# TestSprite AI Testing Report (MCP)

---

## 1️⃣ Document Metadata
- **Project Name:** pesantren-platform
- **Date:** 2026-06-27
- **Target:** Local instance `http://localhost:8000` (central domain) served via FrankenPHP (production mode)
- **Scope:** Central public surface only (landing, login form, legal/info pages). No tenant subdomains, no data writes, no transactions.
- **Subset executed:** 4 of 20 generated test cases (representative smoke)
- **Prepared by:** TestSprite AI + Claude Code

---

## 2️⃣ Requirement Validation Summary

### Requirement: Public Navigation & Content
Visitors can open the landing page, reach the sign-in page, and read informational/legal pages.

#### Test TC001 — Open the landing page and access staff sign-in
- **Test Code:** [TC001](./tmp/TC001_Open_the_landing_page_and_access_staff_sign_in.py)
- **Result:** ✅ Passed
- **Visualization:** https://www.testsprite.com/dashboard/mcp/tests/27428fa8-cd3b-4b1d-bf74-2996270c9bb6/b8e22e34-615f-49e8-bf0d-30d0d1beb5bf
- **Analysis:** The landing page renders and the path to the staff sign-in (`/masuk`) is reachable from it. Core entry flow works.

#### Test TC002 — Read the privacy policy and return to the landing page
- **Test Code:** [TC002](./tmp/TC002_Read_the_privacy_policy_and_return_to_the_landing_page.py)
- **Result:** ✅ Passed
- **Visualization:** https://www.testsprite.com/dashboard/mcp/tests/27428fa8-cd3b-4b1d-bf74-2996270c9bb6/798f15db-b2e8-4a0a-8506-c56c07ece962
- **Analysis:** `/kebijakan-privasi` renders readable content and back-navigation to the landing page works. Legal page wiring is correct.

### Requirement: Authentication Form Behavior
The sign-in form enforces validation and reports authentication failures without crashing.

#### Test TC014 — Show required-field validation on empty sign-in form
- **Test Code:** [TC014](./tmp/TC014_Show_required_field_validation_on_empty_sign_in_form.py)
- **Result:** ✅ Passed
- **Visualization:** https://www.testsprite.com/dashboard/mcp/tests/27428fa8-cd3b-4b1d-bf74-2996270c9bb6/514d7fac-f4c2-4248-8cd9-61dd3bbee8c2
- **Analysis:** Submitting the empty form surfaces required-field validation rather than proceeding. Client/server validation is in place.

#### Test TC016 — Show authentication error for incorrect password
- **Test Code:** [TC016](./tmp/TC016_Show_authentication_error_for_incorrect_password.py)
- **Result:** ✅ Passed
- **Visualization:** https://www.testsprite.com/dashboard/mcp/tests/27428fa8-cd3b-4b1d-bf74-2996270c9bb6/b06404e4-902e-4014-ac89-d7689423c9d7
- **Analysis:** Invalid credentials produce a clean authentication error message (no 500, no leak). Failure path is handled gracefully.

---

## 3️⃣ Coverage & Matching Metrics

- **100.00%** of executed tests passed (4/4)
- **2 credits/test** consumed (150 → 142)

| Requirement                     | Total Tests | ✅ Passed | ❌ Failed |
|---------------------------------|-------------|-----------|-----------|
| Public Navigation & Content     | 2           | 2         | 0         |
| Authentication Form Behavior    | 2           | 2         | 0         |
| **Total**                       | **4**       | **4**     | **0**     |

> 16 further plan cases (additional legal pages, documentation browsing, invalid-email validation, public PPDB/donation pages) were generated but not executed in this subset run.

---

## 4️⃣ Key Gaps / Risks

- **Coverage is intentionally narrow.** Only the central public surface was tested. The highest-value, bug-prone areas — login → role resolver into Filament panels, the 41-group atomic RBAC sidebar, and money/Ledger flows — were **not** exercised because they live on tenant subdomains and/or require an authenticated session.
- **Multi-tenant tunnel limitation.** TestSprite reaches the app through a single `localhost:8000` tunnel, which only resolves the central domain. Tenant admin panels and tenant public sites (e.g. `demo.localhost`) are unreachable this way. Testing them needs a per-tenant base URL or host-mapped tunnel.
- **No central login success path.** The seeded admin (`admin@demo.localhost`) lives in the demo tenant DB, so a successful central login could not be tested — only validation and failure handling.
- **Environment caveat (local only).** Fast serving on Windows+Docker required `opcache.validate_timestamps=0`; with it on, code edits are not picked up without an opcache reset/container restart. This setting is for the test instance, not production.

### Recommended next steps
1. Run the remaining 16 public-surface cases (~32 credits) for a fuller smoke of central pages.
2. To reach login→panel and RBAC navigation (the real value), create a central super_admin and/or point TestSprite at a tenant subdomain base URL.
3. Keep money/transaction tests on a throwaway local tenant DB only — never production.

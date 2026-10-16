# Bus Booking Frontend-Backend Integration Bugfix Design

## Overview

Generated bus booking sites have a complete frontend (`BusBookingWidget.php`) and backend API (`api/business-ops.php`), but they are disconnected. The bug manifests when users attempt to interact with the booking widget - all API calls fail with "Booking service is not ready" because `window.WCBiz` is undefined.

The root cause is that `AutoSiteGenerator::generate()` does not call `business_runtime_inject()` for bus booking sites, leaving the integration layer missing from the generated HTML. The fix will detect bus booking sites during generation and inject the `BusinessRuntime.php` integration layer, which provides `window.WCBiz` API methods pointing to the backend.

This is a targeted, minimal fix that preserves all existing functionality for non-bus sites while enabling the complete booking workflow for bus sites.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when a bus booking site is generated without runtime injection
- **Property (P)**: The desired behavior - bus booking sites should have `window.WCBiz` defined with working API methods
- **Preservation**: Non-bus sites must remain completely unaffected; existing published sites must continue working
- **AutoSiteGenerator::generate()**: The function in `includes/AutoSiteGenerator.php` that creates complete dynamic websites with frontend, backend, and admin panel
- **business_runtime_inject()**: The function in `includes/BusinessRuntime.php` that injects the integration layer (runtime JavaScript) into HTML, connecting frontend to backend
- **wc_business_requires_bus_booking()**: Detection function in `includes/BusinessRuntime.php` that determines if a business spec requires bus booking functionality
- **BusBookingWidget**: The frontend widget in `includes/BusBookingWidget.php` that renders the interactive booking interface and expects `window.WCBiz`
- **window.WCBiz**: JavaScript object providing API methods (api, create, hold, pay, ticket, etc.) that connect frontend to `api/business-ops.php`
- **BusinessSpec**: Array containing business profile, category, entities, and capabilities used to determine what runtime features to inject

## Bug Details

### Bug Condition

The bug manifests when a bus booking website is generated via `AutoSiteGenerator::generate()`. The generated site includes `BusBookingWidget.php` markup in the HTML and has a working backend API at `api/business-ops.php`, but the integration layer connecting them is missing.

The `BusBookingWidget` expects `window.WCBiz.api()` to be available for all booking operations (searching trips, holding seats, creating bookings, payment), but because `business_runtime_inject()` was never called, the runtime JavaScript defining `window.WCBiz` is not present in the generated `index.html`.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type GenerationRequest (business spec + user input)
  OUTPUT: boolean
  
  RETURN wc_business_requires_bus_booking(input.spec) == true
         AND siteGenerated(input) == true
         AND business_runtime_inject_called(input) == false
         AND generatedHTML.contains('data-wc-live-bus-booking') == true
         AND generatedHTML.contains('data-wc-biz-runtime') == false
END FUNCTION
```

### Examples

**Example 1: Bus company site generation**
- Input: `company_name="City Express", project_type="bus booking", primary_purpose="online bus ticket booking"`
- Expected: Generated site has working booking widget with `window.WCBiz` defined
- Actual: Widget renders but all API calls fail with "Booking service is not ready"
- Root cause: `business_runtime_inject()` not called in `AutoSiteGenerator::generate()`

**Example 2: Transport company site**
- Input: `company_name="Coast Transport", project_type="coach booking", primary_purpose="seat reservations"`
- Expected: `window.WCBiz.api('list', {entity: 'trips'})` returns trip data
- Actual: `window.WCBiz` is undefined, JavaScript error thrown
- Root cause: Runtime injection missing from generation flow

**Example 3: Multi-route bus service**
- Input: Business spec with entities `['trips', 'bookings']` and category `'bus'`
- Expected: Seat hold, booking create, and payment methods available via `window.WCBiz`
- Actual: Console shows "Cannot read property 'api' of undefined"
- Root cause: `business_runtime_inject()` is available but never invoked during generation

**Example 4: Edge case - Restaurant site (non-bus)**
- Input: `company_name="Tasty Bites", project_type="restaurant", primary_purpose="online ordering"`
- Expected: Restaurant ordering works without bus booking features
- Actual: Works correctly (bug does NOT manifest for non-bus sites)
- Preservation requirement: This must continue working after the fix

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Non-bus sites (restaurants, hotels, salons, generic, etc.) must continue to generate exactly as before with no runtime injection unless they need it
- Existing published bus sites (if any) must continue to function - the fix only affects NEW generations
- The `business_runtime_inject()` function itself must remain unchanged - we only add the call to it
- Admin panel generation, entity seeding, and all other AutoSiteGenerator features must work identically
- Preview environments, manual publishing, and other code paths using `business_runtime_inject()` must remain unaffected

**Scope:**
All generation requests that do NOT satisfy `wc_business_requires_bus_booking()` should be completely unaffected by this fix. This includes:
- Restaurant sites with `data-wc-form="order"`
- Hotel sites with `data-wc-form="booking"` (room bookings, not bus bookings)
- Salon/appointment sites with `data-wc-form="appointment"`
- Generic contact/inquiry sites
- Sites with custom entities that don't include trips + bookings combination

## Hypothesized Root Cause

Based on the bug description and code analysis, the most likely issues are:

1. **Missing Runtime Injection Call**: `AutoSiteGenerator::generate()` builds the site HTML via `buildDynamicWebsiteHtml()` but does not call `business_runtime_inject()` before writing `index.html`
   - Lines 88-92 in `AutoSiteGenerator.php` show legacy comments about `injectBusinessRuntime()` (now renamed)
   - The function exists and is used in preview/publish flows (verified in grep results)
   - Simply never added to the generator flow after BusinessRuntime was implemented

2. **Missing Business Spec Construction**: Even if we call `business_runtime_inject()`, we need to pass the correct business spec
   - The function signature is `business_runtime_inject(string $html, array $spec, string $slug): string`
   - We have `$slug` available and `$manifest` which contains the spec data
   - Need to extract or construct the spec array from `$manifest` before calling

3. **Category Detection Timing**: The runtime injection must happen AFTER `buildDynamicWebsiteHtml()` includes the bus widget
   - Current flow: detect category → build HTML with widget → write file
   - Fixed flow: detect category → build HTML with widget → inject runtime → write file
   - The injection must detect bus booking requirement and add both runtime JS AND widget if missing

4. **Widget Injection Logic**: `business_runtime_inject()` is supposed to inject the widget when it detects bus booking
   - Line 668-669 in `BusinessRuntime.php`: checks for bus booking AND missing widget
   - If `AutoSiteGenerator` already includes the widget via `renderDynamicWidget()`, the runtime injection should detect it and only add the JS
   - Need to verify the spec passed contains enough information for `wc_business_requires_bus_booking()` to return true

## Correctness Properties

Property 1: Bug Condition - Bus Booking Runtime Injection

_For any_ business generation request where the business spec requires bus booking functionality (as determined by `wc_business_requires_bus_booking()`), the generated site HTML SHALL contain the runtime integration layer with `window.WCBiz` defined and all API methods (api, create, hold, pay, ticket, gotoStep, ticketQR, printTicket) connected to `api/business-ops.php`.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Non-Bus Site Generation

_For any_ business generation request where the business spec does NOT require bus booking functionality, the fixed `AutoSiteGenerator::generate()` SHALL produce exactly the same HTML output as the original function, preserving all existing behavior for restaurants, hotels, salons, generic sites, and any other non-bus business types.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `includes/AutoSiteGenerator.php`

**Function**: `public static function generate(array $input): array`

**Specific Changes**:

1. **Construct Business Spec After Manifest Generation** (after line ~72):
   ```php
   // Construct business spec for runtime injection
   $businessSpec = [
       'category' => $category,
       'slug' => $slug,
       'profile' => [
           'business_name' => $bizName,
           'domain' => $category,
           'description' => $purpose . ' ' . $uvp,
       ],
       'business' => [
           'category' => $category,
           'name' => $bizName,
       ],
       'entities' => $manifest['entities'] ?? [],
       'capabilities' => $manifest['capabilities'] ?? [],
   ];
   ```

2. **Inject Runtime Before Writing HTML** (replace lines ~88-92):
   - Current code:
     ```php
     // Inject Business Runtime & Safety Net
     if (function_exists('injectBusinessRuntime')) {
         $siteHtml = injectBusinessRuntime($siteHtml);
     }
     ```
   - Fixed code:
     ```php
     // Inject Business Runtime & Safety Net
     if (function_exists('business_runtime_inject')) {
         $siteHtml = business_runtime_inject($siteHtml, $businessSpec, $slug);
     }
     ```

3. **Remove Legacy Function Call** (line 91):
   - The legacy `injectBusinessRuntime()` function (if it exists) is no longer needed
   - `business_runtime_inject()` is the current, maintained version
   - Remove the check for the old function name

4. **Ensure BusinessRuntime.php is Required** (verify line 17):
   - Already present: `require_once __DIR__ . '/BusinessRuntime.php';`
   - No change needed, just confirmation

5. **Pass Correct Parameters**:
   - Parameter 1: `$siteHtml` - the generated HTML string
   - Parameter 2: `$businessSpec` - the spec array we constructed
   - Parameter 3: `$slug` - the site slug for API routing
   - The function will call `wc_business_requires_bus_booking($businessSpec)` internally and inject appropriately

### Implementation Flow

The fixed generation flow will be:

```
1. Detect category from user input
   ↓
2. Generate slug and create target directory
   ↓
3. Build business manifest with entities
   ↓
4. Construct business spec array from manifest
   ↓
5. Generate dynamic HTML with category-specific widgets
   ↓
6. Call business_runtime_inject($siteHtml, $businessSpec, $slug)
   ├─ Checks wc_business_requires_bus_booking($businessSpec)
   ├─ If true: injects window.WCBiz runtime + bus widget if missing
   └─ If false: only injects generic form/history runtime
   ↓
7. Apply safety net (existing step)
   ↓
8. Write to index.html
   ↓
9. Generate admin panel (unchanged)
```

### Validation Points

After implementing the fix, verify:

1. **Bus booking sites have runtime**: Search generated HTML for `data-wc-biz-runtime` script tag
2. **window.WCBiz is defined**: Check for `window.WCBiz={` in the runtime script
3. **API methods are present**: Verify `api:function`, `create:function`, `hold:function`, `pay:function` in WCBiz object
4. **Backend URL is correct**: Confirm `OPS_API` points to correct relative path `api/business-ops.php`
5. **Bus detection works**: Confirm `IS_BUS_BOOKING` variable matches `wc_business_requires_bus_booking()` result
6. **Non-bus sites unchanged**: Compare before/after HTML for restaurant site (should be identical or nearly identical with only generic runtime)

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code (verify bug exists), then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Generate a bus booking site using the UNFIXED `AutoSiteGenerator::generate()`, then inspect the generated `index.html` file. Run browser-based tests simulating user interactions with the booking widget.

**Test Cases**:
1. **City Express Bus Site**: Generate with `company_name="City Express"`, `project_type="bus booking"` (will fail on unfixed code)
   - Check generated HTML for `data-wc-biz-runtime` (expect: missing)
   - Open in browser, try to search trips (expect: "Booking service is not ready" error)
   - Open console, check `window.WCBiz` (expect: undefined)

2. **Coach Transport Site**: Generate with category detection yielding `'bus'` or `'transport'` (will fail on unfixed code)
   - Verify `BusBookingWidget` HTML is present in output
   - Verify runtime script is missing
   - Attempt seat hold operation (expect: JavaScript error)

3. **Manual Runtime Injection Test**: Take unfixed generated HTML and manually call `business_runtime_inject()` on it (will succeed, proving runtime works)
   - Construct spec: `['category' => 'bus', 'entities' => [['key' => 'trips'], ['key' => 'bookings']]]`
   - Call `business_runtime_inject($generatedHtml, $spec, 'test-slug')`
   - Verify output now contains `data-wc-biz-runtime` and `window.WCBiz`
   - This confirms the function works, just isn't being called

4. **Manifest Analysis**: Dump the `$manifest` variable during generation (will show spec data is available)
   - Verify manifest contains entities array
   - Verify category is correctly detected as 'bus'
   - This confirms we have the data needed to construct the spec

**Expected Counterexamples**:
- Generated bus sites missing the runtime integration layer
- `window.WCBiz` undefined in browser console
- All booking operations failing with "Booking service is not ready"
- Possible causes: runtime injection call missing, spec construction wrong, category detection failing

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds (bus booking sites), the fixed function produces the expected behavior (working runtime integration).

**Pseudocode:**
```
FOR ALL input WHERE wc_business_requires_bus_booking(constructSpec(input)) DO
  generatedSite := AutoSiteGenerator_fixed::generate(input)
  html := readFile(generatedSite.targetDir + '/index.html')
  ASSERT html.contains('data-wc-biz-runtime')
  ASSERT html.contains('window.WCBiz')
  ASSERT html.contains('IS_BUS_BOOKING=(true)')
  ASSERT html.contains('api/business-ops.php')
  
  // Functional test
  browser := openBrowser(generatedSite.live_url)
  ASSERT browser.hasGlobal('WCBiz')
  ASSERT typeof browser.WCBiz.api === 'function'
  ASSERT typeof browser.WCBiz.hold === 'function'
  ASSERT typeof browser.WCBiz.pay === 'function'
END FOR
```

**Test Cases**:
1. **Standard Bus Booking Site**: Generate with explicit bus-related keywords
   - Input: `company_name="Express Travel", project_type="bus booking system"`
   - Verify: Runtime injected, `window.WCBiz` available, widget interactive

2. **Transport Company Site**: Generate with transport category
   - Input: Business description containing "coach routes and reservations"
   - Verify: `wc_business_requires_bus_booking()` returns true, runtime injected

3. **Trip + Booking Entities**: Generate site with entities that imply bus booking
   - Input: Manifest with entities `['trips', 'bookings', 'passengers']`
   - Verify: Detection logic identifies bus booking need, injects runtime

4. **Browser Functional Test**: Load generated site and test actual booking flow
   - Generate site, start local server, open in browser
   - Click "Find buses" (should call `window.WCBiz.api('list', {entity: 'trips'})`)
   - Verify network request goes to `api/business-ops.php`
   - Verify response is handled without JavaScript errors

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold (non-bus sites), the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT wc_business_requires_bus_booking(constructSpec(input)) DO
  htmlOriginal := AutoSiteGenerator_original::generate(input).html
  htmlFixed := AutoSiteGenerator_fixed::generate(input).html
  
  // Allow for generic runtime injection (forms/history) - focus on bus-specific parts
  ASSERT htmlFixed.contains('data-wc-live-bus-booking') == false
  ASSERT htmlFixed.contains('IS_BUS_BOOKING=(true)') == false
  ASSERT htmlFixed.contains('bus-seat-grid') == false
  
  // Core functionality preserved
  ASSERT htmlFixed.contains('<form') == htmlOriginal.contains('<form')
  ASSERT extractEntities(htmlFixed) == extractEntities(htmlOriginal)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain (different categories, business types)
- It catches edge cases that manual unit tests might miss (unusual category names, empty fields, special characters)
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Generate multiple non-bus sites using UNFIXED code, save their HTML. Generate same sites using FIXED code. Compare outputs focusing on bus-specific elements (should be absent in both).

**Test Cases**:

1. **Restaurant Site Preservation**: Generate restaurant with menu/ordering
   - Input: `company_name="Tasty Bites", project_type="restaurant", features="online ordering"`
   - Verify: No bus booking runtime, standard form wiring only
   - Compare: HTML structure matches unfixed version

2. **Hotel Site Preservation**: Generate hotel with room bookings
   - Input: `company_name="Grand Hotel", project_type="hotel", features="room reservations"`
   - Verify: Room booking forms work, no bus-specific code
   - Note: Uses generic booking forms, not bus widget

3. **Salon Site Preservation**: Generate salon with appointments
   - Input: `company_name="Style Salon", project_type="beauty salon", features="appointment booking"`
   - Verify: Appointment forms work, no transport-related features
   - Compare: Admin panel entities match unfixed version

4. **Generic Business Site**: Generate minimal site without specific category
   - Input: `company_name="ABC Services", project_type="", features="contact form"`
   - Verify: Basic contact form, generic runtime (if any)
   - Compare: Identical output to unfixed code

5. **Property-Based Test**: Generate 50 random non-bus business specs
   - Generate category combinations excluding bus/transport/coach
   - For each: verify no bus widget, no `IS_BUS_BOOKING=true`
   - Statistical confidence that preservation holds across input space

### Unit Tests

- Test `wc_business_requires_bus_booking()` with various spec configurations
  - Explicit category: `['category' => 'bus']` → true
  - Domain hint: `['profile' => ['domain' => 'transport']]` → true
  - Entity combination: `['entities' => [['key' => 'trips'], ['key' => 'bookings']]]` → true
  - Restaurant spec: `['category' => 'restaurant']` → false
  - Hotel spec: `['category' => 'hotel', 'entities' => [['key' => 'bookings']]]` → false (only bookings, no trips)

- Test spec construction from manifest
  - Input: manifest with category, entities, capabilities
  - Output: spec array with correct structure for `business_runtime_inject()`
  - Verify: all required fields present (`category`, `profile`, `entities`)

- Test runtime injection with different specs
  - Bus spec + HTML with widget → runtime added, widget preserved
  - Bus spec + HTML without widget → runtime + widget both added
  - Non-bus spec + HTML → generic runtime only, no bus features

### Property-Based Tests

- Generate random business specs across all categories
  - Verify: `wc_business_requires_bus_booking()` returns true only for bus/transport/coach categories or trip+booking entity combinations
  - Verify: all other categories return false

- Generate random HTML content with/without booking widgets
  - Apply `business_runtime_inject()` with various specs
  - Verify: idempotency (calling twice produces same result)
  - Verify: no duplicate script tags, no duplicate widgets

- Generate random site generation requests
  - Input space: all category combinations, various feature descriptions
  - Verify: fixed generator always produces valid HTML
  - Verify: admin panel always generated correctly
  - Verify: no regressions in entity seeding, metadata files

### Integration Tests

- Full generation flow: user input → published site → functional test
  - Test: Generate City Express bus site, verify booking flow works end-to-end
  - Test: Generate restaurant site, verify ordering works, no bus features
  - Test: Generate hotel site, verify room booking works independently

- Backend integration test: frontend API calls reach backend correctly
  - Start PHP development server for generated site
  - Use Selenium/Playwright to automate browser interactions
  - Test: Click "Find buses" → network request to `api/business-ops.php` → trips returned
  - Test: Hold seat → request with hold action → hold ref returned
  - Test: Confirm booking → request with create action → booking record created

- Admin panel integration: verify admin can manage bookings
  - Generate bus site with admin panel
  - Log into admin panel with generated credentials
  - Add trip via admin interface
  - Verify: trip appears on frontend booking widget
  - Create booking via frontend
  - Verify: booking appears in admin panel

- Cross-browser testing: verify runtime works in all modern browsers
  - Test generated bus site in Chrome, Firefox, Safari, Edge
  - Verify: `window.WCBiz` available in all browsers
  - Verify: booking flow works without JavaScript errors
  - Verify: fallback behavior if JavaScript disabled (graceful degradation)

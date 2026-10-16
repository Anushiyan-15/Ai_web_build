# Bugfix Requirements Document

## Introduction

WebCraft AI's bus booking website generator creates both frontend customer interfaces and backend admin panels, but these components are not connected. When the system generates a bus booking site, the admin panel and customer-facing website operate as isolated components with no data synchronization. This results in non-functional booking websites where:

- Trips added by administrators in the backend do not appear on the customer-facing frontend
- Frontend displays static demo data instead of live database content
- Customer bookings do not persist to the backend database
- Administrators cannot view, manage, or fulfill actual customer bookings

This is a critical defect that renders all generated bus booking websites completely non-operational for their intended business purpose. The bug affects the entire bus booking site generation pipeline in `AutoSiteGenerator.php` and the runtime integration layer in `BusinessRuntime.php`.

**Bug Condition:** A bus booking site is generated without proper integration between the frontend widget (`BusBookingWidget.php`) and backend operations API (`api/business-ops.php`), resulting in disconnected systems.

**Affected Components:**
- `includes/AutoSiteGenerator.php` - Site generation without runtime injection
- `includes/BusinessRuntime.php` - Runtime integration layer
- `includes/BusBookingWidget.php` - Frontend booking widget
- `api/business-ops.php` - Backend operations API
- Published sites (e.g., `published/{slug}/index.html`)

## Bug Analysis

### Current Behavior (Defect)

#### 1. Trip Visibility

1.1 WHEN an administrator adds a new trip through the backend admin panel THEN the trip does NOT appear on the customer-facing frontend website

1.2 WHEN a customer visits the frontend booking page THEN static demo trip data is displayed instead of live trips from the database

1.3 WHEN the frontend searches for available trips THEN the `WCBiz.api()` call fails because `WCBiz` is not defined in the generated HTML

#### 2. Booking Data Flow

1.4 WHEN a customer completes a booking on the frontend THEN the booking data does NOT save to the backend database

1.5 WHEN a customer clicks "Book Now" or "Confirm booking" THEN JavaScript errors occur due to missing `WCBiz` runtime object

1.6 WHEN an administrator checks the bookings list in the admin panel THEN no customer bookings appear because none are saved

#### 3. Runtime Integration

1.7 WHEN `AutoSiteGenerator::generate()` creates a bus booking site THEN the generated `index.html` does NOT include the `BusinessRuntime.php` integration script

1.8 WHEN the generated site HTML is loaded in a browser THEN the `window.WCBiz` object is undefined causing all API calls to fail

1.9 WHEN the frontend widget calls `window.WCBiz.api('list', {entity: 'trips'})` THEN it returns error "Booking service is not ready"

#### 4. Admin-Frontend Synchronization

1.10 WHEN an administrator updates trip details (departure time, price, capacity) THEN the changes do NOT propagate to the frontend

1.11 WHEN an administrator marks a booking as "paid" in the backend THEN the payment status does NOT update on the customer's ticket view

1.12 WHEN trip capacity is exhausted through bookings THEN the frontend does NOT reflect seat unavailability

### Expected Behavior (Correct)

#### 2. Trip Visibility - Fixed

2.1 WHEN an administrator adds a new trip through the backend admin panel THEN the trip SHALL immediately appear on the customer-facing frontend when the page is loaded or refreshed

2.2 WHEN a customer visits the frontend booking page THEN live trips from the database SHALL be displayed through API calls to `api/business-ops.php`

2.3 WHEN the frontend searches for available trips THEN the `WCBiz.api('list', {entity: 'trips'})` call SHALL successfully retrieve trips from the backend

#### 3. Booking Data Flow - Fixed

2.4 WHEN a customer completes a booking on the frontend THEN the booking data SHALL save to the backend database via `api/business-ops.php`

2.5 WHEN a customer clicks "Book Now" or "Confirm booking" THEN the booking SHALL be processed successfully and a booking reference SHALL be returned

2.6 WHEN an administrator checks the bookings list in the admin panel THEN all customer bookings SHALL appear with complete details (passenger, seats, payment status)

#### 4. Runtime Integration - Fixed

2.7 WHEN `AutoSiteGenerator::generate()` creates a bus booking site THEN the generated `index.html` SHALL include the `BusinessRuntime.php` integration script that defines `window.WCBiz`

2.8 WHEN the generated site HTML is loaded in a browser THEN the `window.WCBiz` object SHALL be defined with all required methods (`api`, `hold`, `create`, `cancel`, `pay`, `ticketQR`, `printTicket`, `gotoStep`)

2.9 WHEN the frontend widget calls `window.WCBiz.api()` THEN it SHALL successfully communicate with `api/business-ops.php` to retrieve or submit data

#### 5. Admin-Frontend Synchronization - Fixed

2.10 WHEN an administrator updates trip details (departure time, price, capacity) THEN the changes SHALL be reflected on the frontend when trips are loaded via API

2.11 WHEN an administrator marks a booking as "paid" in the backend THEN the payment status SHALL be retrievable via the ticket lookup functionality

2.12 WHEN trip capacity is exhausted through bookings THEN the frontend SHALL reflect seat unavailability through the availability API endpoint

### Unchanged Behavior (Regression Prevention)

#### 6. Non-Bus Site Generation

3.1 WHEN generating a non-bus website (restaurant, portfolio, etc.) THEN the system SHALL CONTINUE TO generate sites without bus booking runtime injection

3.2 WHEN `wc_business_requires_bus_booking()` returns false THEN the system SHALL CONTINUE TO skip bus-specific runtime integration

#### 7. Backend Admin Panel

3.3 WHEN an administrator accesses the backend admin panel THEN the panel SHALL CONTINUE TO function independently for trip and booking management

3.4 WHEN an administrator uses admin-specific features (reports, payment recording, trip management) THEN these SHALL CONTINUE TO work without modification

#### 8. BusinessOps API

3.5 WHEN the `api/business-ops.php` endpoint receives valid requests THEN it SHALL CONTINUE TO process them correctly (list, create, update, availability, hold, release_hold, cancel, pay)

3.6 WHEN `bops_dispatch()` processes business operations THEN it SHALL CONTINUE TO enforce authorization rules and data validation

#### 9. Existing Published Sites

3.7 WHEN an existing non-bus site is accessed THEN it SHALL CONTINUE TO function without regression

3.8 WHEN existing business runtime features (contact forms, newsletter signup, general booking) are used THEN they SHALL CONTINUE TO work correctly

#### 10. Bus Booking Widget HTML Structure

3.9 WHEN `bus_booking_widget_html()` generates widget markup THEN it SHALL CONTINUE TO produce the same HTML structure with search forms, seat selection, and booking confirmation

3.10 WHEN the bus booking widget JavaScript executes THEN it SHALL CONTINUE TO handle seat selection, hold management, and form submission logic

#### 11. Data Storage and Mirroring

3.11 WHEN bookings or trips are created/updated THEN the system SHALL CONTINUE TO mirror data to the admin directory via `bops_mirror_admin()`

3.12 WHEN the BusinessOps file-based storage system operates THEN it SHALL CONTINUE TO provide atomic transactions with flock protection

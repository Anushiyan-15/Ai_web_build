'use strict';
/* server/lib/business.js — extensible business-category registry.
 * Every category declares: entities (id/label/icon/fields), workflows,
 * adminModules, customerFlows. Unknown categories fall back to a generic
 * profile built from signals — never a fixed one-size list.
 * Also: manifest builder + spec-driven analyzer (gap findings) + safe apply
 * (new entity modules only: features.json + data file + audit + backup).
 */
const CATEGORIES = {
  bus: { label: 'Bus booking & transport',
    entities: [
      { id: 'trips', name: 'Trips', icon: '🚌', fields: ['route', 'date', 'departure', 'price', 'total_seats'] },
      { id: 'bookings', name: 'Bookings', icon: '🎫', fields: ['trip_id', 'seats', 'passenger', 'phone', 'status', 'ref'] },
      { id: 'payments', name: 'Payments', icon: '🧾', fields: ['booking_ref', 'method', 'amount', 'status'] },
      { id: 'buses', name: 'Buses', icon: '🚍', fields: ['plate', 'capacity', 'layout'] },
    ],
    workflows: ['search → select trip → hold seats (15 min) → details → confirm → ticket ref → pay (counter/online) → cancel/release'],
    adminModules: ['Dashboard', 'Trips & schedules', 'Bookings', 'Payments', 'Buses & seats', 'Tickets', 'Settings'] },
  ecommerce: { label: 'E-commerce & retail',
    entities: [
      { id: 'products', name: 'Products', icon: '🛍️', fields: ['title', 'price', 'stock', 'category'] },
      { id: 'orders', name: 'Orders', icon: '🧾', fields: ['customer', 'items', 'total', 'status'] },
      { id: 'customers', name: 'Customers', icon: '👥', fields: ['name', 'phone', 'email'] },
      { id: 'coupons', name: 'Coupons', icon: '🏷️', fields: ['code', 'percent', 'status'] },
    ],
    workflows: ['browse → cart → checkout → pay → fulfill → history'],
    adminModules: ['Dashboard', 'Products', 'Orders', 'Customers', 'Coupons', 'Settings'] },
  restaurant: { label: 'Restaurant & food',
    entities: [
      { id: 'menu_items', name: 'Menu items', icon: '🍽️', fields: ['name', 'price', 'category'] },
      { id: 'orders', name: 'Orders', icon: '🧾', fields: ['customer', 'items', 'total', 'status'] },
      { id: 'reservations', name: 'Reservations', icon: '📅', fields: ['name', 'phone', 'date', 'time', 'guests', 'status'] },
    ],
    workflows: ['browse menu → order → confirm → serve', 'reserve table → confirm → seat'],
    adminModules: ['Dashboard', 'Menu', 'Orders', 'Reservations', 'Settings'] },
  hotel: { label: 'Hotel & accommodation',
    entities: [
      { id: 'rooms', name: 'Rooms', icon: '🏠', fields: ['type', 'rate', 'status'] },
      { id: 'reservations', name: 'Reservations', icon: '📅', fields: ['guest', 'room', 'checkin', 'checkout', 'status'] },
      { id: 'payments', name: 'Payments', icon: '🧾', fields: ['booking_ref', 'method', 'amount', 'status'] },
    ],
    workflows: ['search rooms → reserve → check-in/out → bill'],
    adminModules: ['Dashboard', 'Rooms', 'Reservations', 'Billing', 'Settings'] },
  salon: { label: 'Salon & beauty',
    entities: [
      { id: 'services', name: 'Services', icon: '💅', fields: ['name', 'price', 'duration'] },
      { id: 'staff', name: 'Staff', icon: '👥', fields: ['name', 'role', 'phone'] },
      { id: 'appointments', name: 'Appointments', icon: '📅', fields: ['customer', 'service', 'date', 'time', 'status'] },
    ],
    workflows: ['pick service → pick slot → confirm → remind → complete/cancel'],
    adminModules: ['Dashboard', 'Services', 'Staff', 'Appointments', 'Settings'] },
  clinic: { label: 'Clinic & appointments',
    entities: [
      { id: 'doctors', name: 'Doctors', icon: '🩺', fields: ['name', 'specialization', 'phone'] },
      { id: 'appointments', name: 'Appointments', icon: '📅', fields: ['patient', 'doctor', 'date', 'time', 'status'] },
      { id: 'patients', name: 'Patients', icon: '👥', fields: ['name', 'phone', 'notes'] },
    ],
    workflows: ['request slot → confirm → visit → follow-up'],
    adminModules: ['Dashboard', 'Doctors', 'Appointments', 'Patients', 'Settings'] },
  realestate: { label: 'Real estate',
    entities: [
      { id: 'properties', name: 'Properties', icon: '🏠', fields: ['title', 'type', 'price', 'location'] },
      { id: 'inquiries', name: 'Inquiries', icon: '✉️', fields: ['name', 'phone', 'property', 'message'] },
    ],
    workflows: ['browse listings → enquire → viewing → close'],
    adminModules: ['Dashboard', 'Properties', 'Inquiries', 'Settings'] },
  education: { label: 'Education & courses',
    entities: [
      { id: 'courses', name: 'Courses', icon: '🎓', fields: ['title', 'fee', 'duration'] },
      { id: 'enrollments', name: 'Enrollments', icon: '📝', fields: ['student', 'course', 'status'] },
      { id: 'tutors', name: 'Tutors', icon: '👥', fields: ['name', 'subject', 'phone'] },
    ],
    workflows: ['browse courses → enroll → track → complete'],
    adminModules: ['Dashboard', 'Courses', 'Enrollments', 'Tutors', 'Settings'] },
  events: { label: 'Events & ticketing',
    entities: [
      { id: 'events', name: 'Events', icon: '🎪', fields: ['title', 'date', 'venue', 'capacity'] },
      { id: 'tickets', name: 'Tickets', icon: '🎫', fields: ['event', 'holder', 'ref', 'status'] },
    ],
    workflows: ['browse events → reserve seats → ticket ref → check-in'],
    adminModules: ['Dashboard', 'Events', 'Tickets', 'Settings'] },
  portfolio: { label: 'Portfolio & agency',
    entities: [
      { id: 'projects', name: 'Projects', icon: '🗂️', fields: ['title', 'category', 'link'] },
      { id: 'inquiries', name: 'Inquiries', icon: '✉️', fields: ['name', 'email', 'message'] },
    ],
    workflows: ['browse work → enquire → quote → deliver'],
    adminModules: ['Dashboard', 'Projects', 'Inquiries', 'Settings'] },
  rental: { label: 'Rental & hire',
    entities: [
      { id: 'units', name: 'Units', icon: '🔑', fields: ['name', 'rate', 'status'] },
      { id: 'rentals', name: 'Rentals', icon: '📝', fields: ['customer', 'unit', 'from', 'to', 'status'] },
    ],
    workflows: ['browse units → reserve dates → handover → return'],
    adminModules: ['Dashboard', 'Units', 'Rentals', 'Settings'] },
  automotive: { label: 'Automotive sales & service',
    entities: [
      { id: 'vehicles', name: 'Vehicles', icon: '🚗', fields: ['make', 'model', 'price', 'status'] },
      { id: 'service_jobs', name: 'Service jobs', icon: '🔧', fields: ['customer', 'vehicle', 'issue', 'status'] },
    ],
    workflows: ['browse stock → enquire/test-drive → buy', 'book service → diagnose → repair → deliver'],
    adminModules: ['Dashboard', 'Vehicles', 'Service jobs', 'Settings'] },
  subscription: { label: 'Subscription & membership',
    entities: [
      { id: 'plans', name: 'Plans', icon: '💳', fields: ['name', 'price', 'interval'] },
      { id: 'subscribers', name: 'Subscribers', icon: '👥', fields: ['name', 'email', 'plan', 'status'] },
    ],
    workflows: ['pick plan → subscribe → renew/cancel'],
    adminModules: ['Dashboard', 'Plans', 'Subscribers', 'Settings'] },
  travel: { label: 'Travel & tours',
    entities: [
      { id: 'packages', name: 'Tour Packages', icon: '🧳', fields: ['title', 'destination', 'price', 'capacity'] },
      { id: 'departures', name: 'Departures', icon: '📅', fields: ['package', 'date', 'seats'] },
      { id: 'bookings', name: 'Reservations', icon: '🎫', fields: ['package', 'name', 'phone', 'status'] },
    ],
    workflows: ['browse packages → reserve seats → confirm → cancel per policy'],
    adminModules: ['Dashboard', 'Packages', 'Departures', 'Reservations', 'Settings'] },
  agency: { label: 'Agency & professional services',
    entities: [
      { id: 'services', name: 'Services', icon: '💼', fields: ['name', 'description', 'price'] },
      { id: 'inquiries', name: 'Enquiries', icon: '✉️', fields: ['name', 'email', 'message'] },
      { id: 'appointments', name: 'Consultations', icon: '📅', fields: ['customer', 'date', 'time', 'status'] },
    ],
    workflows: ['browse services → request quote/slot → confirm → deliver'],
    adminModules: ['Dashboard', 'Services', 'Enquiries', 'Consultations', 'Settings'] },
  professional: { label: 'Professional / other services',
    entities: [
      { id: 'services', name: 'Services', icon: '💼', fields: ['title', 'description'] },
      { id: 'inquiries', name: 'Inquiries', icon: '✉️', fields: ['name', 'email', 'message'] },
    ],
    workflows: ['browse → enquire → respond'],
    adminModules: ['Dashboard', 'Services', 'Inquiries', 'Settings'] },
  generic: { label: 'General business',
    entities: [
      { id: 'services', name: 'Services', icon: '💼', fields: ['title', 'description'] },
      { id: 'inquiries', name: 'Inquiries', icon: '✉️', fields: ['name', 'email', 'message'] },
    ],
    workflows: ['browse → enquire → respond'],
    adminModules: ['Dashboard', 'Services', 'Inquiries', 'Settings'] },
};

function detectCategory(text) {
  const t = String(text || '').toLowerCase();
  const hit = (...words) => words.some(w => t.includes(w));
  if (hit('bus', 'coach', 'ticket', 'seat', 'transport', 'travel', 'tour', 'logistic')) return 'bus';
  if (hit('tour package', 'tour ', 'travel agency', 'destination', 'itinerary', 'holiday package')) return 'travel';
  if (hit('shop', 'store', 'product', 'cart', 'ecommerce', 'e-commerce', 'retail', 'fashion', 'boutique', 'grocery')) return 'ecommerce';
  if (hit('restaurant', 'food', 'menu', 'pizza', 'cafe', 'bakery', 'dining')) return 'restaurant';
  if (hit('hotel', 'room', 'stay', 'resort', 'villa', 'accommodation')) return 'hotel';
  if (hit('salon', 'beauty', 'spa', 'barber', 'nail')) return 'salon';
  if (hit('clinic', 'doctor', 'dental', 'hospital', 'patient', 'physio')) return 'clinic';
  if (hit('propert', 'real estate', 'plot', 'villa sale', 'rent house', 'apartment')) return 'realestate';
  if (hit('course', 'school', 'tuition', 'class', 'training', 'academy', 'tutor')) return 'education';
  if (hit('event', 'concert', 'wedding hall', 'conference', 'exhibition')) return 'events';
  if (hit('portfolio', 'agency', 'design', 'photograph', 'freelance')) return 'portfolio';
  if (hit('rent', 'hire', 'lease', 'equipment')) return 'rental';
  if (hit('car', 'auto', 'garage', 'vehicle', 'service center')) return 'automotive';
  if (hit('member', 'subscription', 'gym', 'fitness', 'club')) return 'subscription';
  return 'generic';
}

function manifestFor(slug, bizName, category, extraEntities) {
  const cat = CATEGORIES[category] || CATEGORIES.generic;
  const entities = [...cat.entities];
  for (const e of extraEntities || []) {
    if (e && e.id && !entities.some(x => x.id === e.id)) entities.push(e);
  }
  return {
    slug, business: bizName || slug, category,
    label: cat.label, entities, workflows: cat.workflows,
    adminModules: cat.adminModules, updated_at: new Date().toISOString(),
  };
}

// Spec-driven analyzer: manifest + HTML → findings + safe plans.
// Only proposes registry-known modules; never invents backend behavior.
function analyze(manifest, html) {
  const findings = [];
  const H = String(html || '');
  const ML = H.replace(/<script\b[\s\S]*?<\/script\s*>/gi, '').replace(/<style\b[\s\S]*?<\/style\s*>/gi, '');
  const forms = ML.match(/<form\b[^>]*>/gi) || [];
  const bare = forms.filter(t => !/data-wc-form|business-ops|node-api|contact\.php/i.test(t));
  if (bare.length) findings.push({ area: 'forms', severity: 'high', detail: `${bare.length} form(s) submit nowhere`, plan: 'wire_forms' });
  for (const e of manifest.entities || []) {
    if (!new RegExp(e.id.replace(/_/g, '[_\\s-]?'), 'i').test(ML)) {
      findings.push({ area: 'module:' + e.id, severity: 'medium', detail: `No UI markers for “${e.name}”`, plan: 'manual' });
    }
  }
  if (!/wc-biz-runtime|node-api|business-ops/i.test(H)) {
    findings.push({ area: 'runtime', severity: 'high', detail: 'No frontend→backend wiring detected', plan: 'runtime' });
  }
  return { findings, auto_fixable: findings.filter(f => f.plan === 'wire_forms' || f.plan === 'runtime').length };
}

// Public catalog entities mirror the PHP registry allowlist
// (business_registry_is_public): safe for anonymous search. Never add
// bookings/orders/payments/patients/subscribers/customers/inquiries.
const PUBLIC_ENTITIES = ['trips', 'routes', 'buses', 'products', 'coupons', 'menu_items',
  'rooms', 'departures', 'packages', 'events', 'tickets', 'properties',
  'viewings', 'courses', 'services', 'vehicles', 'units', 'plans',
  'doctors', 'staff', 'tutors', 'projects'];

function stripSecrets(r) {
  const out = {};
  for (const [k, val] of Object.entries(r || {})) {
    if (/pass|hash|secret|token|otp/i.test(k)) continue;
    out[k] = val;
  }
  return out;
}

// Scalar query filters match case-insensitive substrings; cap applied.
// Domain synonyms: frontend search (origin/destination) must match
// admin-managed fields (route/departure/...) and vice versa.
const FILTER_ALIASES = {
  origin: ['origin', 'route', 'departure', 'from', 'source'],
  destination: ['destination', 'route', 'arrival', 'to'],
  route: ['route', 'origin', 'destination', 'departure'],
};
function publicFilter(rows, params, cap) {
  // Routing keys are transport, never record filters (a client sending
  // ?entity=trips must not filter everything out).
  const skip = new Set(['email', 'phone', 'limit', 'entity', 'slug', 'action']);
  const filters = {};
  for (const [k, val] of Object.entries(params || {})) {
    if (skip.has(k) || typeof val !== 'string' || !val.trim()) continue;
    filters[k.toLowerCase()] = val.trim().toLowerCase();
  }
  const out = [];
  for (const r of rows || []) {
    if (!r || typeof r !== 'object') continue;
    let ok = true;
    for (const [fk, fv] of Object.entries(filters)) {
      const keys = FILTER_ALIASES[fk] || [fk];
      let rv = null;
      for (const [rk, val] of Object.entries(r)) {
        if (keys.includes(String(rk).toLowerCase()) && (typeof val === 'string' || typeof val === 'number')) {
          rv = (rv === null ? '' : rv + ' ') + String(val).toLowerCase();
        }
      }
      if (rv === null || !rv.includes(fv)) { ok = false; break; }
    }
    if (ok) out.push(stripSecrets(r));
    if (out.length >= (cap || 100)) break;
  }
  return out;
}

module.exports = { CATEGORIES, detectCategory, manifestFor, analyze, PUBLIC_ENTITIES, publicFilter };

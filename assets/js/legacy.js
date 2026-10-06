/* ═══════════════════════════════════════════════════════════
   WEBbuilder.lk – JavaScript Application
   ═══════════════════════════════════════════════════════════ */

'use strict';

// ─── Global State ───────────────────────────────────────────
const state = {
  currentStep: 1,
  totalSteps: 7,
  requirement: '',
  businessType: '',
  selectedColor: 'blue',
  selectedDesign: null,
  selectedPayment: null,
  approved: false,
  customizations: {
    siteName: '',
    tagline: '',
    description: '',
    whatsapp: false,
    logo: '',
    colors: '',
    sections: []
  },
  feedback: [],
  designsGenerated: false,
  currentPreviewDesign: null
};

// ─── Design Templates ───────────────────────────────────────
const designTemplates = [
  {
    id: 1,
    name: 'Design 1',
    style: 'Modern',
    class: 'design-modern',
    description: 'Clean, bold layout with full-width hero section and card-based content grid',
    features: ['Full-width hero', 'Card grid layout', 'Bold typography', 'CTA sections']
  },
  {
    id: 2,
    name: 'Design 2',
    style: 'Creative',
    class: 'design-creative',
    description: 'Dynamic, dark-themed layout with gradient accents and immersive visuals',
    features: ['Dark theme', 'Gradient accents', 'Visual storytelling', 'Animated elements']
  },
  {
    id: 3,
    name: 'Design 3',
    style: 'Minimal',
    class: 'design-minimal',
    description: 'Clean, whitespace-focused layout with elegant typography and subtle details',
    features: ['Minimal aesthetic', 'Elegant fonts', 'Focus on content', 'Professional look']
  }
];

// ─── Example Prompts ────────────────────────────────────────
const examplePrompts = {
  restaurant: `I need a modern website for my restaurant in Colombo. I want Home, Menu, About Us, Gallery and Contact pages. Use a warm red and gold color theme. Include a reservation form and showcase our Sri Lankan cuisine. Make it look professional and appetizing.`,
  clinic: `I need a professional medical clinic website. I want pages for Home, Services, Doctors, Appointments, and Contact. Use a clean blue and white color scheme. Include an online booking form and patient information section. Trustworthy and calming design.`,
  realestate: `I need a real estate agency website with property listings. I want Home, Properties, About, Team, and Contact pages. Use a modern navy blue theme. Include a property search filter and featured listings showcase. Professional and elegant look.`,
  salon: `I need a beauty salon website for my salon in Kandy. I want Home, Services, Gallery, Team, and Booking pages. Use a luxurious pink and gold color scheme. Include an online appointment booking system. Glamorous and sophisticated design.`,
  hotel: `I need a boutique hotel website to attract tourists. I want Home, Rooms, Amenities, Gallery, and Contact pages. Use a tropical green and gold theme. Include a room booking system and location map. Warm, inviting and resort-like atmosphere.`
};

// ─── DOM Ready ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initColorSwatches();
  initThemePills();
  initHamburger();
  initStepPills();
  renderDesignCards();
  renderChooseDesigns();
  updateProgress();
});

// ═══════════════════════════════════════════════════════════
// THEME MANAGEMENT
// ═══════════════════════════════════════════════════════════

function initTheme() {
  const saved = localStorage.getItem('wb_theme') || 'light';
  setTheme(saved);
}

function setTheme(theme) {
  document.body.setAttribute('data-theme', theme);
  const btn = document.getElementById('themeToggle');
  if (btn) {
    btn.querySelector('.theme-icon').textContent = theme === 'dark' ? '☀️' : '🌙';
    btn.querySelector('.theme-label').textContent = theme === 'dark' ? 'Light' : 'Dark';
  }
  localStorage.setItem('wb_theme', theme);
}

document.getElementById('themeToggle').addEventListener('click', () => {
  const current = document.body.getAttribute('data-theme');
  setTheme(current === 'dark' ? 'light' : 'dark');
});

// ─── Color Theme ────────────────────────────────────────────
function initColorSwatches() {
  document.querySelectorAll('.swatch').forEach(s => {
    s.addEventListener('click', () => {
      document.querySelectorAll('.swatch').forEach(x => x.classList.remove('active'));
      s.classList.add('active');
      state.selectedColor = s.dataset.color;
      setColorTheme(state.selectedColor);
    });
  });
}

function setColorTheme(color) {
  document.documentElement.setAttribute('data-color', color);
  // Sync theme pills
  document.querySelectorAll('.theme-pill').forEach(p => {
    p.classList.toggle('active', p.dataset.themeColor === color);
  });
  // Sync swatches
  document.querySelectorAll('.swatch').forEach(s => {
    s.classList.toggle('active', s.dataset.color === color);
  });
  state.selectedColor = color;
  // Re-render previews if designs are generated
  if (state.designsGenerated) {
    renderDesignCards();
    renderChooseDesigns();
    renderFeedbackPreview();
    renderFinalPreview();
  }
}

function initThemePills() {
  document.querySelectorAll('.theme-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      setColorTheme(pill.dataset.themeColor);
    });
  });
}

// ═══════════════════════════════════════════════════════════
// NAVIGATION
// ═══════════════════════════════════════════════════════════

function initHamburger() {
  const hamburger = document.getElementById('hamburger');
  const nav = document.getElementById('mainNav');
  hamburger.addEventListener('click', () => {
    nav.classList.toggle('open');
  });
}

function initStepPills() {
  document.querySelectorAll('.step-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      const target = pill.dataset.target;
      const stepNum = parseInt(target.replace('step', ''));
      if (stepNum <= state.currentStep || state.designsGenerated) {
        goToStep(stepNum);
      }
    });
  });
}

function scrollToStep1() {
  document.getElementById('step1').scrollIntoView({ behavior: 'smooth' });
  goToStep(1);
}

// ─── Step Navigation ─────────────────────────────────────
function goToStep(stepNum) {
  // Validate
  if (stepNum < 1 || stepNum > state.totalSteps) return;
  if (stepNum === 2 && !state.designsGenerated) {
    showToast('Please generate designs first!', 'warning');
    return;
  }

  // Hide current
  document.querySelectorAll('.builder-step').forEach(s => s.classList.remove('active'));

  // Show target
  const target = document.getElementById(`step${stepNum}`);
  if (target) {
    target.classList.add('active');
    state.currentStep = stepNum;
    updateProgress();
    updateStepPills();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Render step-specific content
    if (stepNum === 3) renderFeedbackPreview();
    if (stepNum === 4) renderChooseDesigns();
    if (stepNum === 5) renderFinalPreview();
  }
}

function updateProgress() {
  const pct = (state.currentStep / state.totalSteps) * 100;
  const fill = document.getElementById('progressFill');
  const label = document.getElementById('progressLabel');
  if (fill) fill.style.width = pct + '%';
  if (label) label.textContent = `Step ${state.currentStep} of ${state.totalSteps}`;
}

function updateStepPills() {
  document.querySelectorAll('.step-pill').forEach((pill, i) => {
    pill.classList.toggle('active', i + 1 === state.currentStep);
  });
}

// ═══════════════════════════════════════════════════════════
// STEP 1 – REQUIREMENT
// ═══════════════════════════════════════════════════════════

function updateCharCount(el) {
  const count = document.getElementById('charCount');
  if (count) count.textContent = el.value.length;
}

function fillPrompt(type) {
  const ta = document.getElementById('requirementText');
  if (ta && examplePrompts[type]) {
    ta.value = examplePrompts[type];
    updateCharCount(ta);
    // Scroll to textarea
    ta.scrollIntoView({ behavior: 'smooth', block: 'center' });
    ta.focus();
    showToast('Example prompt filled! Customize it and generate.', 'info');
  }
}

async function generateDesigns() {
  const ta = document.getElementById('requirementText');
  const businessType = document.getElementById('businessType');

  if (!ta.value.trim() || ta.value.trim().length < 10) {
    showToast('Please describe your website idea in at least 10 characters.', 'error');
    ta.focus();
    return;
  }

  state.requirement = ta.value.trim();
  state.businessType = businessType ? businessType.value : '';
  state.customizations.siteName = extractSiteName(state.requirement);

  // Show loading
  showLoading('AI is analyzing your requirements...');

  // Simulate AI processing
  await simulateAIProcessing([
    { text: 'Understanding your business...', duration: 600 },
    { text: 'Generating design concepts...', duration: 700 },
    { text: 'Applying your color preferences...', duration: 500 },
    { text: 'Finalizing 3 unique designs...', duration: 400 }
  ]);

  hideLoading();

  state.designsGenerated = true;

  // Update designs with actual data
  renderDesignCards();
  renderChooseDesigns();

  // Go to step 2
  goToStep(2);
  showToast('✨ 3 unique designs generated successfully!', 'success');
}

function extractSiteName(text) {
  // Try to extract a business name from the requirement
  const patterns = [
    /(?:for my |called |named |website for )([A-Z][a-zA-Z\s]{2,25}?)(?:\.|,| in| at| with)/i,
    /my ([A-Z][a-zA-Z\s]{2,20}?) (?:website|business|shop|restaurant|clinic|hotel|salon)/i
  ];
  for (const pat of patterns) {
    const m = text.match(pat);
    if (m) return m[1].trim();
  }
  return 'My Business';
}

// ═══════════════════════════════════════════════════════════
// STEP 2 – DESIGN CARDS
// ═══════════════════════════════════════════════════════════

function renderDesignCards() {
  const grid = document.getElementById('designsGrid');
  if (!grid) return;

  grid.innerHTML = designTemplates.map(d => createDesignCard(d)).join('');
}

function createDesignCard(design) {
  const isSelected = state.selectedDesign && state.selectedDesign.id === design.id;
  const siteName = state.customizations.siteName || 'Your Business';

  return `
    <div class="design-card ${design.class} ${isSelected ? 'selected' : ''}" id="dcard${design.id}" onclick="previewDesign(${design.id})">
      <div class="card-preview">
        ${createMiniSite(design, siteName)}
        <div class="selected-badge">✓</div>
      </div>
      <div class="card-info">
        <div class="card-name">${design.name}</div>
        <div class="card-style">${design.style}</div>
        <div class="card-actions">
          <button class="btn-preview" onclick="event.stopPropagation(); openPreviewModal(${design.id})">Preview</button>
          <button class="btn-choose ${isSelected ? 'selected' : ''}" onclick="event.stopPropagation(); chooseDesign(${design.id})">
            ${isSelected ? '✓ Chosen' : 'Choose'}
          </button>
        </div>
      </div>
    </div>
  `;
}

function createMiniSite(design, siteName = 'Your Business') {
  if (design.style === 'Minimal') {
    return `
      <div class="mini-site">
        <div class="mini-nav-row">
          <div class="mini-logo"></div>
          <div class="mini-nav-links"><span class="ml"></span><span class="ml"></span><span class="ml"></span></div>
        </div>
        <div class="mini-hero">
          <div class="mini-h1 w70"></div>
          <div class="mini-h1 w50"></div>
          <div class="mini-btn"></div>
        </div>
        <div class="mini-cards-row">
          <div class="mini-card-sm"></div><div class="mini-card-sm"></div><div class="mini-card-sm"></div>
        </div>
      </div>`;
  }
  return `
    <div class="mini-site">
      <div class="mini-nav-row">
        <div class="mini-logo"></div>
        <div class="mini-nav-links"><span class="ml"></span><span class="ml"></span><span class="ml"></span><span class="ml"></span></div>
      </div>
      <div class="mini-hero">
        <div class="mini-h1 w70"></div>
        <div class="mini-h1 w50"></div>
        <div class="mini-btn"></div>
      </div>
      <div class="mini-cards-row">
        <div class="mini-card-sm"></div><div class="mini-card-sm"></div><div class="mini-card-sm"></div>
      </div>
    </div>`;
}

// ─── Design Selection ────────────────────────────────────────
function chooseDesign(id) {
  const design = designTemplates.find(d => d.id === id);
  if (!design) return;

  state.selectedDesign = design;

  // Update all design cards
  document.querySelectorAll('.design-card').forEach(card => card.classList.remove('selected'));
  const card = document.getElementById(`dcard${id}`);
  if (card) card.classList.add('selected');

  // Update choose designs
  document.querySelectorAll('.choose-card').forEach(c => c.classList.remove('selected'));
  const cc = document.getElementById(`ccard${id}`);
  if (cc) cc.classList.add('selected');

  showToast(`Design ${id} (${design.style}) selected!`, 'success');
  goToStep(3);
}

function previewDesign(id) {
  openPreviewModal(id);
}

// ─── Preview Modal ────────────────────────────────────────
function openPreviewModal(id) {
  const design = designTemplates.find(d => d.id === id);
  if (!design) return;

  state.currentPreviewDesign = id;
  const title = document.getElementById('previewModalTitle');
  const content = document.getElementById('previewModalContent');

  if (title) title.textContent = `${design.name} – ${design.style} Style`;
  if (content) content.innerHTML = createFullPreview(design);

  openModal('previewModal');
}

function chooseDesignFromPreview() {
  if (state.currentPreviewDesign) {
    chooseDesign(state.currentPreviewDesign);
    closeModal('previewModal');
  }
}

function createFullPreview(design) {
  const siteName = state.customizations.siteName || 'Your Business';
  const isDark = design.style === 'Creative';
  const isMinimal = design.style === 'Minimal';
  const navBg = isDark ? '#0F172A' : isMinimal ? '#fff' : 'var(--accent)';
  const heroBg = isDark
    ? 'linear-gradient(135deg, #0F172A, #1E293B)'
    : isMinimal
    ? 'linear-gradient(135deg, #F8FAFC, #F1F5F9)'
    : 'linear-gradient(135deg, var(--accent), var(--accent-dark))';
  const textColor = (isDark || !isMinimal) ? '#fff' : 'var(--text)';

  return `
    <div class="full-preview">
      <div class="preview-nav" style="background:${navBg}; border-bottom:1px solid rgba(255,255,255,0.1)">
        <div class="preview-logo" style="color:${isMinimal ? 'var(--accent)' : '#fff'}">${siteName}</div>
        <div class="preview-nav-links">
          <a style="color:${isMinimal ? 'var(--text-muted)' : 'rgba(255,255,255,0.8)'}">Home</a>
          <a style="color:${isMinimal ? 'var(--text-muted)' : 'rgba(255,255,255,0.8)'}">Menu</a>
          <a style="color:${isMinimal ? 'var(--text-muted)' : 'rgba(255,255,255,0.8)'}">About</a>
          <a style="color:${isMinimal ? 'var(--text-muted)' : 'rgba(255,255,255,0.8)'}">Contact</a>
        </div>
      </div>
      <div class="preview-hero" style="background:${heroBg}; color:${textColor}">
        <h2>${getSiteHeadline(siteName)}</h2>
        <p>${getSiteSubtitle()}</p>
        <div class="preview-hero-btns">
          <div class="ph-btn" style="${isMinimal ? 'background:var(--accent);color:#fff' : ''}">
            ${getHeroCTA()}
          </div>
          ${state.customizations.whatsapp ? '<div class="whatsapp-badge">💬 WhatsApp</div>' : ''}
        </div>
      </div>
      <div class="preview-menu">
        <h3>Our Special Menu</h3>
        <div class="preview-grid">
          ${Array(6).fill('<div class="preview-item"></div>').join('')}
        </div>
      </div>
    </div>`;
}

function getSiteHeadline(name) {
  const type = state.businessType || 'business';
  const headlines = {
    'Restaurant / Cafe': `Authentic Flavors at ${name}`,
    'Healthcare / Clinic': `Your Health, Our Priority`,
    'Real Estate': `Find Your Dream Property`,
    'Hotel / Tourism': `Experience Paradise at ${name}`,
    'Education / Training': `Learn & Grow with ${name}`,
    'Beauty / Salon': `Beauty Redefined at ${name}`,
  };
  return headlines[type] || `Welcome to ${name}`;
}

function getSiteSubtitle() {
  const type = state.businessType || '';
  const subtitles = {
    'Restaurant / Cafe': 'Fresh Ingredients · Traditional Taste · Unforgettable Moments',
    'Healthcare / Clinic': 'Expert Care · Modern Facilities · Compassionate Service',
    'Real Estate': 'Premium Properties · Expert Agents · Best Deals',
    'Hotel / Tourism': 'Luxury Rooms · Scenic Views · Exceptional Service',
  };
  return subtitles[type] || 'Quality Service · Professional Team · Customer First';
}

function getHeroCTA() {
  const type = state.businessType || '';
  const ctas = {
    'Restaurant / Cafe': 'View Menu',
    'Healthcare / Clinic': 'Book Appointment',
    'Real Estate': 'View Properties',
    'Hotel / Tourism': 'Book Now',
  };
  return ctas[type] || 'Get Started';
}

// ═══════════════════════════════════════════════════════════
// STEP 3 – FEEDBACK
// ═══════════════════════════════════════════════════════════

function renderFeedbackPreview() {
  const preview = document.getElementById('feedbackPreview');
  if (!preview) return;

  const design = state.selectedDesign || designTemplates[0];
  preview.innerHTML = `<div style="padding:0">${createFullPreview(design)}</div>`;
}

function sendFeedback() {
  const input = document.getElementById('feedbackInput');
  if (!input || !input.value.trim()) return;

  const text = input.value.trim();
  input.value = '';
  addChatMessage(text, 'client');
  state.feedback.push(text);

  // Simulate AI processing
  setTimeout(() => {
    addChatMessage('Processing your feedback...', 'ai-typing');
    setTimeout(() => {
      const response = generateAIResponse(text);
      updateLastAIMessage(response);
      applyFeedbackToDesign(text);
    }, 1200);
  }, 400);
}

function quickFeedback(text) {
  const input = document.getElementById('feedbackInput');
  if (input) {
    input.value = text;
    sendFeedback();
  }
}

function addChatMessage(text, type) {
  const container = document.getElementById('chatMessages');
  if (!container) return;

  const isClient = type === 'client';
  const isTyping = type === 'ai-typing';

  const msgEl = document.createElement('div');
  msgEl.className = `chat-msg ${isClient ? 'client-msg' : ''}`;
  msgEl.id = isTyping ? 'typing-msg' : '';
  msgEl.innerHTML = `
    <div class="chat-avatar ${isClient ? 'client' : 'ai'} sm">${isClient ? '👤' : 'AI'}</div>
    <div class="msg-bubble ${isClient ? 'client-bubble' : ''} ${isTyping ? 'ai-update-bubble' : ''}">${text}</div>
  `;

  container.appendChild(msgEl);
  container.scrollTop = container.scrollHeight;
}

function updateLastAIMessage(text) {
  const typing = document.getElementById('typing-msg');
  if (typing) {
    typing.id = '';
    const bubble = typing.querySelector('.msg-bubble');
    if (bubble) {
      bubble.className = 'msg-bubble ai-update-bubble';
      bubble.textContent = '✓ AI Update: ' + text;
    }
  } else {
    addChatMessage('✓ AI Update: ' + text, 'ai');
  }
}

function generateAIResponse(feedback) {
  const lower = feedback.toLowerCase();
  if (lower.includes('color') || lower.includes('blue') || lower.includes('red') || lower.includes('green')) {
    return 'Color scheme updated based on your preference!';
  }
  if (lower.includes('whatsapp') || lower.includes('wp')) {
    state.customizations.whatsapp = true;
    renderFeedbackPreview();
    return 'WhatsApp button added to the design!';
  }
  if (lower.includes('logo')) return 'Logo placeholder updated. You can upload your actual logo when we build the site.';
  if (lower.includes('modern') || lower.includes('clean')) return 'Design updated for a more modern, clean aesthetic!';
  if (lower.includes('section') || lower.includes('add') || lower.includes('remove')) return 'Sections adjusted based on your requirements!';
  if (lower.includes('text') || lower.includes('content') || lower.includes('modify')) return 'Text content updated to match your brand voice!';
  return 'Design updated based on your feedback! The changes will be reflected in your final website.';
}

function applyFeedbackToDesign(feedback) {
  const lower = feedback.toLowerCase();
  // Color changes
  const colorMap = { blue: 'blue', green: 'green', red: 'red', purple: 'purple', orange: 'orange' };
  for (const [key, val] of Object.entries(colorMap)) {
    if (lower.includes(key)) { setColorTheme(val); break; }
  }
  // Re-render preview
  renderFeedbackPreview();
}

// ═══════════════════════════════════════════════════════════
// STEP 4 – CHOOSE DESIGN
// ═══════════════════════════════════════════════════════════

function renderChooseDesigns() {
  const container = document.getElementById('chooseDesigns');
  if (!container) return;

  container.innerHTML = designTemplates.map(d => {
    const isSelected = state.selectedDesign && state.selectedDesign.id === d.id;
    const heroBg = d.style === 'Creative'
      ? 'linear-gradient(135deg, #0F172A, var(--accent))'
      : d.style === 'Minimal'
      ? 'linear-gradient(135deg, var(--bg-card), var(--bg-card2))'
      : 'linear-gradient(135deg, var(--accent), var(--accent-dark))';

    return `
      <div class="choose-card ${isSelected ? 'selected' : ''}" id="ccard${d.id}" onclick="selectDesign(${d.id})">
        <div class="choose-preview" style="background:${heroBg}; position:relative">
          ${createMiniSite(d)}
          <div class="choose-badge">✓</div>
        </div>
        <div class="choose-info">
          <div class="choose-name">${d.name}</div>
          <div class="choose-style">${d.style}</div>
          <button class="btn-select" onclick="event.stopPropagation(); selectDesign(${d.id})">
            ${isSelected ? 'Selected' : 'Select'}
          </button>
        </div>
      </div>
    `;
  }).join('');
}

function selectDesign(id) {
  const design = designTemplates.find(d => d.id === id);
  if (!design) return;

  state.selectedDesign = design;

  document.querySelectorAll('.choose-card').forEach(c => c.classList.remove('selected'));
  const card = document.getElementById(`ccard${id}`);
  if (card) card.classList.add('selected');

  // Also mark in step 2 grid
  document.querySelectorAll('.design-card').forEach(c => c.classList.remove('selected'));
  const dc = document.getElementById(`dcard${id}`);
  if (dc) dc.classList.add('selected');

  showToast(`${design.name} (${design.style}) selected!`, 'success');
}

// ─── Customization Modal ─────────────────────────────────
function openCustomizeModal(type) {
  const title = document.getElementById('customizeModalTitle');
  const content = document.getElementById('customizeModalContent');

  const forms = {
    colors: `
      <div class="customize-form">
        <label>Primary Color</label>
        <div class="color-swatches" style="margin-bottom:8px">
          ${['blue', 'green', 'red', 'purple', 'orange'].map(c => `
            <div class="swatch ${state.selectedColor === c ? 'active' : ''}"
              style="background:${{blue:'#2563EB',green:'#16A34A',red:'#DC2626',purple:'#7C3AED',orange:'#EA580C'}[c]}"
              onclick="setColorTheme('${c}'); this.parentElement.querySelectorAll('.swatch').forEach(s=>s.classList.remove('active')); this.classList.add('active')"></div>
          `).join('')}
        </div>
        <label>Custom Hex Color (optional)</label>
        <input type="text" placeholder="#2563EB" value="${state.customizations.colors}" oninput="state.customizations.colors=this.value" />
      </div>`,
    logo: `
      <div class="customize-form">
        <label>Logo URL</label>
        <input type="text" placeholder="https://yoursite.com/logo.png" value="${state.customizations.logo}" oninput="state.customizations.logo=this.value" />
        <label>Business Name</label>
        <input type="text" placeholder="Your Business Name" value="${state.customizations.siteName}" oninput="state.customizations.siteName=this.value" />
        <label>Tagline</label>
        <input type="text" placeholder="Your awesome tagline" value="${state.customizations.tagline}" oninput="state.customizations.tagline=this.value" />
      </div>`,
    sections: `
      <div class="customize-form">
        <label>Select sections to include</label>
        ${['Home/Hero', 'About Us', 'Services/Menu', 'Gallery', 'Team', 'Testimonials', 'Contact Form', 'FAQ', 'Blog'].map(s => `
          <label style="display:flex;align-items:center;gap:10px;font-weight:400;color:var(--text);text-transform:none;letter-spacing:0;font-size:0.9rem;cursor:pointer">
            <input type="checkbox" ${state.customizations.sections.includes(s) ? 'checked' : ''}
              onchange="toggleSection('${s}', this.checked)" style="width:auto;padding:0;border:none;background:none" />
            ${s}
          </label>`).join('')}
      </div>`,
    text: `
      <div class="customize-form">
        <label>Site Name / Business Name</label>
        <input type="text" value="${state.customizations.siteName}" oninput="state.customizations.siteName=this.value" placeholder="Your Business Name" />
        <label>Tagline</label>
        <input type="text" value="${state.customizations.tagline}" oninput="state.customizations.tagline=this.value" placeholder="Short catchy phrase" />
        <label>Business Description</label>
        <textarea oninput="state.customizations.description=this.value" placeholder="What your business does...">${state.customizations.description}</textarea>
      </div>`
  };

  const titles = {
    colors: '🎨 Change Colors',
    logo: '🖼️ Update Logo & Name',
    sections: '➕ Add/Remove Sections',
    text: '✏️ Modify Text Content'
  };

  if (title) title.textContent = titles[type] || 'Customize';
  if (content) content.innerHTML = forms[type] || '';

  openModal('customizeModal');
}

function toggleSection(section, checked) {
  if (checked && !state.customizations.sections.includes(section)) {
    state.customizations.sections.push(section);
  } else {
    state.customizations.sections = state.customizations.sections.filter(s => s !== section);
  }
}

function toggleWhatsApp() {
  state.customizations.whatsapp = !state.customizations.whatsapp;
  showToast(`WhatsApp button ${state.customizations.whatsapp ? 'enabled' : 'disabled'}!`, 'info');
}

function applyCustomization() {
  closeModal('customizeModal');
  // Re-render previews with new customization
  renderFeedbackPreview();
  renderFinalPreview();
  showToast('Customizations applied!', 'success');
}

// ═══════════════════════════════════════════════════════════
// STEP 5 – FINAL APPROVAL
// ═══════════════════════════════════════════════════════════

function renderFinalPreview() {
  const preview = document.getElementById('finalPreviewCard');
  if (!preview) return;

  const design = state.selectedDesign || designTemplates[0];
  preview.innerHTML = createFullPreview(design);
}

function approveDesign() {
  state.approved = true;

  const btn = document.getElementById('approveBtn');
  if (btn) { btn.disabled = true; btn.textContent = '✓ Approved!'; }

  const note = document.getElementById('approvalNote');
  if (note) note.style.display = 'block';

  const nextBtn = document.getElementById('step5Next');
  if (nextBtn) nextBtn.disabled = false;

  showToast('Design approved! Ready to order.', 'success');

  setTimeout(() => goToStep(6), 1500);
}

// ═══════════════════════════════════════════════════════════
// STEP 6 – PAYMENT
// ═══════════════════════════════════════════════════════════

function selectPayment(el, method) {
  document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  state.selectedPayment = method;
}

function placeOrder() {
  if (!state.selectedPayment) {
    showToast('Please select a payment method.', 'warning');
    return;
  }
  if (!state.approved) {
    showToast('Please approve the design first (Step 5).', 'warning');
    goToStep(5);
    return;
  }

  showLoading('Processing your order...');

  setTimeout(() => {
    hideLoading();
    // Update order ID display
    const orderId = 'WBL-2024-' + Math.floor(1000 + Math.random() * 9000);
    const orderIdEl = document.getElementById('orderIdDisplay');
    const projEl = document.getElementById('projectNameDisplay');
    if (orderIdEl) orderIdEl.textContent = orderId;
    if (projEl) projEl.textContent = state.customizations.siteName || 'My Website';

    goToStep(7);
    showToast('🎉 Order placed successfully!', 'success');
  }, 2000);
}

// ═══════════════════════════════════════════════════════════
// STEP 7 – ORDER TRACKING
// ═══════════════════════════════════════════════════════════

function openTrackModal() {
  openModal('trackModal');
}

function resetBuilder() {
  // Reset state
  Object.assign(state, {
    currentStep: 1,
    requirement: '',
    businessType: '',
    selectedDesign: null,
    selectedPayment: null,
    approved: false,
    designsGenerated: false,
    feedback: [],
    customizations: { siteName: '', tagline: '', description: '', whatsapp: false, logo: '', colors: '', sections: [] }
  });

  // Reset UI
  const ta = document.getElementById('requirementText');
  const bt = document.getElementById('businessType');
  if (ta) ta.value = '';
  if (bt) bt.value = '';
  updateCharCount({ value: '' });

  const approveBtn = document.getElementById('approveBtn');
  if (approveBtn) { approveBtn.disabled = false; approveBtn.textContent = '✓ Approve & Proceed'; }

  const note = document.getElementById('approvalNote');
  if (note) note.style.display = 'none';

  const nextBtn = document.getElementById('step5Next');
  if (nextBtn) nextBtn.disabled = true;

  goToStep(1);
  showToast('Starting fresh! Describe your new project.', 'info');
}

// ═══════════════════════════════════════════════════════════
// MODAL HELPERS
// ═══════════════════════════════════════════════════════════

function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal(overlay.id);
  });
});

// Close modals on Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => closeModal(m.id));
  }
});

// ═══════════════════════════════════════════════════════════
// LOADING OVERLAY
// ═══════════════════════════════════════════════════════════

function showLoading(text = 'Processing...') {
  const overlay = document.getElementById('loadingOverlay');
  const loadingText = document.getElementById('loadingText');
  const bar = document.getElementById('loadingBar');

  if (overlay) overlay.classList.add('active');
  if (loadingText) loadingText.textContent = text;
  if (bar) { bar.style.width = '0%'; }

  document.body.style.overflow = 'hidden';
}

function hideLoading() {
  const overlay = document.getElementById('loadingOverlay');
  const bar = document.getElementById('loadingBar');

  if (bar) bar.style.width = '100%';
  setTimeout(() => {
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }, 300);
}

async function simulateAIProcessing(steps) {
  const bar = document.getElementById('loadingBar');
  const text = document.getElementById('loadingText');
  const stepCount = steps.length;
  let completed = 0;

  for (const step of steps) {
    if (text) text.textContent = step.text;
    await sleep(step.duration);
    completed++;
    if (bar) bar.style.width = ((completed / stepCount) * 90) + '%';
  }
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

// ═══════════════════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ═══════════════════════════════════════════════════════════

let toastContainer = null;

function showToast(message, type = 'info') {
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.style.cssText = `
      position: fixed;
      bottom: 90px;
      right: 24px;
      z-index: 5000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    `;
    document.body.appendChild(toastContainer);
  }

  const colors = {
    success: { bg: '#22C55E', icon: '✓' },
    error: { bg: '#EF4444', icon: '✕' },
    warning: { bg: '#F59E0B', icon: '⚠' },
    info: { bg: 'var(--accent)', icon: 'ℹ' }
  };
  const { bg, icon } = colors[type] || colors.info;

  const toast = document.createElement('div');
  toast.style.cssText = `
    background: ${bg};
    color: #fff;
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    pointer-events: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    animation: toastIn 0.3s ease;
    max-width: 320px;
  `;
  toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

  // Add animation keyframes once
  if (!document.getElementById('toast-style')) {
    const style = document.createElement('style');
    style.id = 'toast-style';
    style.textContent = `
      @keyframes toastIn { from { opacity:0; transform:translateX(40px) } to { opacity:1; transform:translateX(0) } }
      @keyframes toastOut { from { opacity:1; transform:translateX(0) } to { opacity:0; transform:translateX(40px) } }
    `;
    document.head.appendChild(style);
  }

  toastContainer.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'toastOut 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ═══════════════════════════════════════════════════════════
// SCROLL & SCROLL-SPY
// ═══════════════════════════════════════════════════════════

window.addEventListener('scroll', () => {
  const header = document.getElementById('siteHeader');
  if (header) {
    header.style.boxShadow = window.scrollY > 20
      ? '0 2px 20px rgba(0,0,0,0.1)'
      : 'none';
  }
});

// ─── Enter key for chat input ─────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const chatInput = document.getElementById('feedbackInput');
  if (chatInput) {
    chatInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') sendFeedback();
    });
  }
});

// ─── Keyboard shortcut: G to generate ─────────────────────
document.addEventListener('keydown', (e) => {
  if (e.ctrlKey && e.key === 'Enter' && state.currentStep === 1) {
    generateDesigns();
  }
});

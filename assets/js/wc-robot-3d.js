/**
 * assets/js/wc-robot-3d.js — Ultra-Premium 360° 3D AI Robot for WebCraft Website Generation
 * 
 * Hyper-advanced procedural 3D robot agent utilizing Three.js (WebGL with graceful fallback).
 * Features:
 *   - Continuous 360-degree rotation showing full 3D robotic form from all angles
 *   - Smooth interactive mouse parallax (subtle 3D tilt tracking cursor)
 *   - Sculpted futuristic chassis (Titanium, Pearl White, Chrome Trim) with glowing reactor core
 *   - Expressive glowing digital visor with animated cyber HUD scanlines & dynamic blinking
 *   - Floating articulated Cyber Halo ring spinning above head
 *   - Floating anti-gravity propulsion pod with pulsing ion glow rings & jet flare
 *   - 4 Dynamic Holographic Website Blueprint Panels:
 *       1. Neural Hero & Smart Navigation
 *       2. Interactive Bento Grid & Component Architecture
 *       3. Responsive Mobile Viewport Engine
 *       4. Live AI Code & Neural Synthesis Terminal (with customer brief)
 *   - Dynamic energy laser construction beams connecting robot hands to floating website panels
 *   - Multi-axis gyroscopic gimbal rings with orbiting data satellites
 *   - Ground holographic radar projection floor with rotating coordinate ticks
 *   - Double-layer cyber particle system (ambient stardust + upward spiraling ion thruster plume)
 *   - Stage-reactive metamorphosis synchronized with WebCraft's 7-stage generation rail
 *   - Brand color reactivity & customer business brief integration
 *   - Lazy rendering loop (runs ONLY when loading overlay is visible)
 *   - Responsive auto-scaling, pointer-events:none background integration
 *   - Respects prefers-reduced-motion
 */
(function(window, document) {
  'use strict';

  var WCRobot3D = {
    isInitialized: false,
    isRunning: false,
    currentStage: 0,
    container: null,
    canvas: null,
    renderer: null,
    scene: null,
    camera: null,
    clock: null,
    animId: null,

    // Hierarchy nodes
    robotRoot: null,
    bodyGroup: null,
    headGroup: null,
    haloRing: null,
    faceVisor: null,
    eyeLeft: null,
    eyeRight: null,
    eyePupilLeft: null,
    eyePupilRight: null,
    coreLight: null,
    coreMesh: null,
    coreTurbine: null,
    leftArm: null,
    rightArm: null,
    leftHand: null,
    rightHand: null,
    thrusterRings: [],
    thrusterJet: null,

    // Holographic & FX nodes
    holoGroup: null,
    groundRadar: null,
    gyroRing1: null,
    gyroRing2: null,
    satellite1: null,
    satellite2: null,
    wireframePanels: [],
    laserBeams: [],
    scannerBeam: null,
    ambientParticles: null,
    thrusterParticles: null,
    chipMeshes: [],

    // Mouse parallax
    mouseTargetX: 0,
    mouseTargetY: 0,
    currentMouseX: 0,
    currentMouseY: 0,

    // State & Timers
    blinkTimer: 0,
    isBlinking: false,
    rotationSpeed: 0.52, // radians per sec (~12s per full 360° turn)
    baseHoverY: 0.16,
    reducedMotion: false,
    businessData: null,
    brandColor: 0x22d3ee,
    stageProgress: 0,

    // ── Story / performance state ──
    storyAct: 'idle',          // idle | walking | reaching | catching | collecting | assembling | complete
    storyTime: 0,              // elapsed seconds inside the current act
    walkCycle: 0,              // walk animation phase
    actStartPct: 0,            // generation pct when the current act began
    cardsCaught: 0,            // how many requirement cards have been collected
    totalCards: 0,             // total floating requirement cards

    // ── Hierarchy nodes (story) ──
    leftLeg: null,
    rightLeg: null,
    reachTarget: null,         // {x,y,z} world target the hands reach toward
    caughtCards: [],           // card meshes currently being pulled in
    collectedParticles: null,  // THREE.Points for particles flowing into the core
    particlePool: [],          // {mesh, target, speed, life} for collection particles
    websiteRoot: null,         // group holding the assembling holographic website
    websiteComponents: [],     // {mesh, targetPos, targetRot, active, delay} website pieces
    cameraOrbitAngle: 0,       // orbital camera angle (radians)
    cameraRadius: 7.2,         // orbital distance from scene centre
    cameraHeight: 0.6,         // orbital camera height

    /**
     * Initializes the 3D scene and robot model
     */
    init: function(containerId, canvasId) {
      if (this.isInitialized) return true;
      if (typeof window.THREE === 'undefined') {
        return false;
      }

      this.container = document.getElementById(containerId || 'wc-robot-3d-canvas-wrap');
      this.canvas = document.getElementById(canvasId || 'wc-robot-3d-canvas');
      if (!this.container || !this.canvas) return false;

      // Check reduced motion preference
      try {
        var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.reducedMotion = mq && mq.matches;
        if (mq && mq.addEventListener) {
          mq.addEventListener('change', function(e) { WCRobot3D.reducedMotion = e.matches; });
        }
      } catch (e) {}

      try {
        // Setup WebGL Renderer with alpha transparency and high fidelity
        this.renderer = new THREE.WebGLRenderer({
          canvas: this.canvas,
          alpha: true,
          antialias: true,
          powerPreference: 'high-performance',
          stencil: false,
          depth: true
        });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.35;

        // Setup Scene & Camera
        this.scene = new THREE.Scene();
        var width = this.container.clientWidth || window.innerWidth;
        var height = this.container.clientHeight || window.innerHeight;
        this.camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 100);
        this.camera.position.set(0, 0.28, 7.0);
        this.camera.lookAt(0, 0.05, 0);

        this.clock = new THREE.Clock();

        // Build Lighting & 3D Objects
        this._setupLighting();
        this._buildRobotModel();
        this._buildHolographicScene();
        this._buildParticleClouds();
        this._buildCollectionParticles();
        this._buildWebsiteAssembly();
        this._setupMouseParallax();

        // Resize listener
        this._onResize = this._handleResize.bind(this);
        window.addEventListener('resize', this._onResize, { passive: true });
        this._handleResize();

        this.isInitialized = true;
        return true;
      } catch (err) {
        console.warn('[WCRobot3D] WebGL initialization skipped:', err && err.message);
        return false;
      }
    },

    /**
     * Mouse parallax listener for subtle interactive 3D perspective
     */
    _setupMouseParallax: function() {
      var self = this;
      var onMouseMove = function(e) {
        if (!self.isRunning) return;
        var w = window.innerWidth || 1;
        var h = window.innerHeight || 1;
        var nx = (e.clientX - w / 2) / (w / 2); // -1 to +1
        var ny = (e.clientY - h / 2) / (h / 2); // -1 to +1
        self.mouseTargetX = nx * 0.45;
        self.mouseTargetY = -ny * 0.25;
      };
      window.addEventListener('mousemove', onMouseMove, { passive: true });
    },

    /**
     * Configures atmospheric cinematic lighting
     */
    _setupLighting: function() {
      var isLight = false;
      try { isLight = document.documentElement.getAttribute('data-theme') === 'light'; } catch (e) {}

      // Soft ambient light
      this.ambientLight = new THREE.AmbientLight(0x0f172a, isLight ? 2.4 : 1.6);
      this.scene.add(this.ambientLight);

      // Key light: cool cyan/blue highlighting metallic specular
      var keyLight = new THREE.DirectionalLight(0xe0f2fe, 2.0);
      keyLight.position.set(4.5, 5.5, 5.0);
      this.scene.add(keyLight);

      // Rim light: vibrant cyber violet for high-tech contour glow
      var rimLight = new THREE.DirectionalLight(0xc084fc, 1.8);
      rimLight.position.set(-4.5, 3.5, -4.5);
      this.scene.add(rimLight);

      // PointLight at robot's chest arc reactor
      this.coreLight = new THREE.PointLight(0x22d3ee, 3.2, 7.5);
      this.coreLight.position.set(0, 0.12, 0.65);
      this.scene.add(this.coreLight);

      // Top cyan accent spotlight
      var topLight = new THREE.SpotLight(0x38bdf8, 1.5, 12, Math.PI / 3.8, 0.45);
      topLight.position.set(0, 6, 2.5);
      this.scene.add(topLight);

      // Bottom thruster upward pointlight
      this.thrusterLight = new THREE.PointLight(0x06b6d4, 2.0, 4.0);
      this.thrusterLight.position.set(0, -1.2, 0);
      this.scene.add(this.thrusterLight);
    },

    /**
     * Creates procedural canvas textures for the 4 holographic blueprint cards
     */
    _createHoloTexture: function(type, label, customText) {
      var cv = document.createElement('canvas');
      cv.width = 512;
      cv.height = 340;
      var ctx = cv.getContext('2d');

      // Glassmorphic translucent cyber gradient card background
      var grad = ctx.createLinearGradient(0, 0, 0, cv.height);
      grad.addColorStop(0, 'rgba(10, 20, 42, 0.88)');
      grad.addColorStop(1, 'rgba(4, 9, 24, 0.94)');
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, cv.width, cv.height);

      // Glowing outer border
      ctx.strokeStyle = '#06b6d4';
      ctx.lineWidth = 3.5;
      ctx.strokeRect(4, 4, cv.width - 8, cv.height - 8);

      // Futuristic corner bracket brackets
      ctx.strokeStyle = '#38bdf8';
      ctx.lineWidth = 6;
      var cs = 26;
      ctx.beginPath(); ctx.moveTo(4, 4 + cs); ctx.lineTo(4, 4); ctx.lineTo(4 + cs, 4); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(cv.width - 4 - cs, 4); ctx.lineTo(cv.width - 4, 4); ctx.lineTo(cv.width - 4, 4 + cs); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(4, cv.height - 4 - cs); ctx.lineTo(4, cv.height - 4); ctx.lineTo(4 + cs, cv.height - 4); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(cv.width - 4 - cs, cv.height - 4); ctx.lineTo(cv.width - 4, cv.height - 4); ctx.lineTo(cv.width - 4, cv.height - 4 - cs); ctx.stroke();

      // Top cyber header bar with status dot
      ctx.fillStyle = 'rgba(56, 189, 248, 0.18)';
      ctx.fillRect(10, 10, cv.width - 20, 38);
      ctx.fillStyle = '#10b981';
      ctx.beginPath(); ctx.arc(26, 29, 5.5, 0, Math.PI * 2); ctx.fill();

      ctx.fillStyle = '#67e8f9';
      ctx.font = 'bold 14px "Fira Code", monospace';
      ctx.fillText(label || 'WEBCRAFT AI BLUEPRINT', 42, 34);

      // Tech ID stamp
      ctx.fillStyle = '#a855f7';
      ctx.font = '11px "Fira Code", monospace';
      ctx.fillText('REV 3.8.4 // LIVE', cv.width - 135, 34);

      if (type === 'hero') {
        // Hero Section & Navigation Blueprint
        // Nav bar wireframe
        ctx.fillStyle = 'rgba(56, 189, 248, 0.3)';
        ctx.fillRect(24, 62, 70, 14);
        ctx.fillStyle = 'rgba(148, 163, 184, 0.4)';
        ctx.fillRect(110, 64, 40, 10);
        ctx.fillRect(160, 64, 40, 10);
        ctx.fillRect(210, 64, 40, 10);
        ctx.fillStyle = '#06b6d4';
        ctx.fillRect(400, 58, 85, 20);

        // Big Hero Title
        ctx.fillStyle = '#f8fafc';
        ctx.font = 'bold 22px system-ui, sans-serif';
        ctx.fillText(customText || 'Transforming Tomorrow', 24, 114);

        // Subtitle lines
        ctx.fillStyle = '#94a3b8';
        ctx.fillRect(24, 134, 380, 10);
        ctx.fillRect(24, 152, 290, 10);

        // Dual CTA Buttons
        ctx.fillStyle = '#6366f1';
        ctx.fillRect(24, 185, 140, 38);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 13px sans-serif';
        ctx.fillText('GET STARTED →', 42, 209);

        ctx.strokeStyle = '#38bdf8';
        ctx.lineWidth = 2;
        ctx.strokeRect(175, 185, 110, 38);
        ctx.fillStyle = '#38bdf8';
        ctx.fillText('LIVE DEMO', 200, 209);

        // Right side Hero image card
        ctx.strokeStyle = 'rgba(168, 85, 247, 0.8)';
        ctx.strokeRect(320, 175, 165, 135);
        ctx.fillStyle = 'rgba(168, 85, 247, 0.18)';
        ctx.fillRect(320, 175, 165, 135);
        ctx.fillStyle = '#c084fc';
        ctx.font = 'bold 12px "Fira Code", monospace';
        ctx.fillText('✦ 3D ASSET MATRIX', 335, 245);

      } else if (type === 'bento') {
        // Dynamic Bento Grid & Components Blueprint
        var bw = 145, bh = 115;
        var cards = [
          { title: 'LIGHTNING SPEED', color: '#06b6d4', icon: '⚡' },
          { title: 'AI AUTONOMY', color: '#a855f7', icon: '🧠' },
          { title: 'SUPABASE DB', color: '#10b981', icon: '🗄️' }
        ];
        for (var i = 0; i < 3; i++) {
          var bx = 22 + i * 162;
          ctx.strokeStyle = 'rgba(56, 189, 248, 0.6)';
          ctx.lineWidth = 2;
          ctx.strokeRect(bx, 68, bw, bh);
          ctx.fillStyle = 'rgba(56, 189, 248, 0.14)';
          ctx.fillRect(bx, 68, bw, bh);

          // Card header badge
          ctx.fillStyle = cards[i].color;
          ctx.beginPath(); ctx.arc(bx + 26, 94, 14, 0, Math.PI * 2); ctx.fill();
          ctx.fillStyle = '#ffffff';
          ctx.font = '13px sans-serif';
          ctx.fillText(cards[i].icon, bx + 19, 99);

          // Card Title & lines
          ctx.fillStyle = '#f8fafc';
          ctx.font = 'bold 11px "Fira Code", monospace';
          ctx.fillText(cards[i].title, bx + 46, 98);

          ctx.fillStyle = '#94a3b8';
          ctx.fillRect(bx + 16, 120, 110, 8);
          ctx.fillRect(bx + 16, 134, 90, 8);
          ctx.fillRect(bx + 16, 148, 100, 8);
        }

        // Bottom stats bar
        ctx.fillStyle = 'rgba(15, 23, 42, 0.9)';
        ctx.fillRect(22, 205, cv.width - 44, 105);
        ctx.strokeStyle = 'rgba(99, 102, 241, 0.5)';
        ctx.strokeRect(22, 205, cv.width - 44, 105);

        ctx.fillStyle = '#38bdf8';
        ctx.font = 'bold 12px "Fira Code", monospace';
        ctx.fillText('✦ REAL-TIME DATABASE TELEMETRY', 38, 230);

        ctx.fillStyle = '#10b981';
        ctx.font = 'bold 20px "Fira Code", monospace';
        ctx.fillText('99.98% FAST', 38, 265);
        ctx.fillStyle = '#c084fc';
        ctx.fillText('+480% REACH', 190, 265);
        ctx.fillStyle = '#38bdf8';
        ctx.fillText('ZERO CONFIG', 345, 265);

      } else if (type === 'mobile') {
        // Responsive Mobile Viewport Blueprint
        var mx = 180, my = 56, mw = 152, mh = 265;
        // Phone outer frame
        ctx.strokeStyle = '#22d3ee';
        ctx.lineWidth = 3.5;
        ctx.strokeRect(mx, my, mw, mh);
        ctx.fillStyle = 'rgba(8, 14, 30, 0.92)';
        ctx.fillRect(mx, my, mw, mh);

        // Dynamic Island notch
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(mx + 46, my + 8, 60, 11);
        ctx.fillStyle = '#38bdf8';
        ctx.beginPath(); ctx.arc(mx + 54, my + 13, 3, 0, Math.PI * 2); ctx.fill();

        // Phone App Header
        ctx.fillStyle = '#6366f1';
        ctx.fillRect(mx + 16, my + 30, mw - 32, 42);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 11px sans-serif';
        ctx.fillText('✦ MOBILE READY', mx + 24, my + 56);

        // Mobile Cards
        ctx.fillStyle = 'rgba(16, 185, 129, 0.25)';
        ctx.fillRect(mx + 16, my + 82, mw - 32, 48);
        ctx.fillStyle = '#34d399';
        ctx.font = 'bold 10px monospace';
        ctx.fillText('100% RESPONSIVE', mx + 24, my + 105);

        ctx.fillStyle = 'rgba(168, 85, 247, 0.25)';
        ctx.fillRect(mx + 16, my + 140, mw - 32, 48);
        ctx.fillStyle = '#c084fc';
        ctx.fillText('TOUCH GESTURES', mx + 24, my + 164);

        // Bottom CTA Button
        ctx.fillStyle = '#06b6d4';
        ctx.fillRect(mx + 25, my + 210, mw - 50, 26);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 10px sans-serif';
        ctx.fillText('EXPLORE APP', mx + 42, my + 227);

      } else if (type === 'code') {
        // Live AI Neural Synthesis & Code Engine
        ctx.fillStyle = 'rgba(2, 6, 23, 0.95)';
        ctx.fillRect(16, 58, cv.width - 32, 260);
        ctx.strokeStyle = '#38bdf8';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(16, 58, cv.width - 32, 260);

        ctx.font = '12px "Fira Code", monospace';
        var lines = [
          { t: '> INITIALIZING NEURAL GENERATOR ENGINE...', c: '#38bdf8' },
          { t: '> TARGET: ' + (customText || 'ENTERPRISE SOLUTION'), c: '#f59e0b' },
          { t: 'const Site = new AIArchitecture({', c: '#e2e8f0' },
          { t: '  mode: "DYNAMIC_FULLSTACK",', c: '#a855f7' },
          { t: '  db: Supabase.connectClient(),', c: '#10b981' },
          { t: '  theme: "RESPONSIVE_GLASS_2026",', c: '#06b6d4' },
          { t: '  components: ["Hero", "Bento", "Shop", "DB"],', c: '#f8fafc' },
          { t: '});', c: '#e2e8f0' },
          { t: '> [OK] 3 BEAUTIFUL VARIATIONS COMPILED.', c: '#34d399' }
        ];

        lines.forEach(function(l, li) {
          ctx.fillStyle = l.c;
          ctx.fillText(l.t, 30, 84 + li * 24);
        });
      }

      var tex = new THREE.CanvasTexture(cv);
      tex.needsUpdate = true;
      return tex;
    },

    /**
     * Builds the ultra-premium 3D Robot model
     */
    _buildRobotModel: function() {
      var THREE = window.THREE;
      this.robotRoot = new THREE.Group();
      this.scene.add(this.robotRoot);

      // Shared High-End Materials
      var armorTitanium = new THREE.MeshStandardMaterial({
        color: 0x0f172a,
        metalness: 0.92,
        roughness: 0.22
      });
      var armorWhite = new THREE.MeshStandardMaterial({
        color: 0xf8fafc,
        metalness: 0.55,
        roughness: 0.16
      });
      var chromeTrim = new THREE.MeshStandardMaterial({
        color: 0x94a3b8,
        metalness: 0.98,
        roughness: 0.10
      });
      var glowCyan = new THREE.MeshBasicMaterial({
        color: 0x22d3ee
      });
      var glowViolet = new THREE.MeshBasicMaterial({
        color: 0xa855f7
      });
      var visorDark = new THREE.MeshStandardMaterial({
        color: 0x020617,
        roughness: 0.08,
        metalness: 0.95
      });
      var glowEyes = new THREE.MeshBasicMaterial({
        color: 0x67e8f9
      });
      var glowPupil = new THREE.MeshBasicMaterial({
        color: 0xffffff
      });

      // ──────────────────────────────────────────
      // 1. TORSO & CHASSIS
      // ──────────────────────────────────────────
      this.bodyGroup = new THREE.Group();
      this.robotRoot.add(this.bodyGroup);

      // Inner Core Torso Chassis
      var chestGeo = new THREE.CylinderGeometry(0.54, 0.40, 1.08, 20);
      var chestMesh = new THREE.Mesh(chestGeo, armorTitanium);
      this.bodyGroup.add(chestMesh);

      // Curved Front Armor Breastplate
      var plateGeo = new THREE.CylinderGeometry(0.57, 0.44, 0.88, 20, 1, false, -Math.PI / 2.7, Math.PI / 1.35);
      var plateMesh = new THREE.Mesh(plateGeo, armorWhite);
      plateMesh.position.set(0, 0.08, 0.05);
      this.bodyGroup.add(plateMesh);

      // Back Engine Generator Pack
      var backPackGeo = new THREE.BoxGeometry(0.58, 0.74, 0.32);
      var backPack = new THREE.Mesh(backPackGeo, armorTitanium);
      backPack.position.set(0, 0.08, -0.35);
      this.bodyGroup.add(backPack);

      // Back cooling vent neon strips
      for (var b = -1; b <= 1; b++) {
        var ventGeo = new THREE.BoxGeometry(0.1, 0.48, 0.04);
        var vent = new THREE.Mesh(ventGeo, glowViolet);
        vent.position.set(b * 0.17, 0.08, -0.52);
        this.bodyGroup.add(vent);
      }

      // Central Arc Reactor Core (Heart)
      var coreFrameGeo = new THREE.CylinderGeometry(0.26, 0.26, 0.12, 28);
      coreFrameGeo.rotateX(Math.PI / 2);
      var coreFrame = new THREE.Mesh(coreFrameGeo, chromeTrim);
      coreFrame.position.set(0, 0.12, 0.48);
      this.bodyGroup.add(coreFrame);

      var coreGlassGeo = new THREE.CylinderGeometry(0.20, 0.20, 0.14, 28);
      coreGlassGeo.rotateX(Math.PI / 2);
      this.coreMesh = new THREE.Mesh(coreGlassGeo, glowCyan);
      this.coreMesh.position.set(0, 0.12, 0.50);
      this.bodyGroup.add(this.coreMesh);

      // Inner Spinning Reactor Turbine Spokes
      var turbineGeo = new THREE.TorusGeometry(0.14, 0.02, 8, 6);
      this.coreTurbine = new THREE.Mesh(turbineGeo, chromeTrim);
      this.coreTurbine.position.set(0, 0.12, 0.56);
      this.bodyGroup.add(this.coreTurbine);

      var coreRingGeo = new THREE.TorusGeometry(0.22, 0.022, 12, 36);
      var coreRing = new THREE.Mesh(coreRingGeo, glowCyan);
      coreRing.position.set(0, 0.12, 0.55);
      this.bodyGroup.add(coreRing);

      // ──────────────────────────────────────────
      // 2. HEAD & VISOR
      // ──────────────────────────────────────────
      this.headGroup = new THREE.Group();
      this.headGroup.position.set(0, 0.98, 0);
      this.bodyGroup.add(this.headGroup);

      // Articulated Hydraulic Neck Collar
      var neckGeo = new THREE.CylinderGeometry(0.24, 0.28, 0.28, 18);
      var neck = new THREE.Mesh(neckGeo, chromeTrim);
      neck.position.set(0, -0.22, 0);
      this.headGroup.add(neck);

      // Sleek Aerodynamic Helmet Shell
      var helmGeo = new THREE.SphereGeometry(0.46, 32, 26);
      helmGeo.scale(1.0, 1.12, 1.18);
      var helm = new THREE.Mesh(helmGeo, armorWhite);
      this.headGroup.add(helm);

      // Cyber Top Crest / Fin
      var crestGeo = new THREE.BoxGeometry(0.07, 0.20, 0.76);
      var crest = new THREE.Mesh(crestGeo, glowCyan);
      crest.position.set(0, 0.48, -0.05);
      this.headGroup.add(crest);

      // Floating Articulated Cyber Halo Ring
      var haloGeo = new THREE.TorusGeometry(0.55, 0.022, 12, 36);
      haloGeo.rotateX(Math.PI / 2.3);
      this.haloRing = new THREE.Mesh(haloGeo, glowCyan);
      this.haloRing.position.set(0, 0.65, 0);
      this.headGroup.add(this.haloRing);

      // Curved Front Faceplate Visor
      var visorGeo = new THREE.SphereGeometry(0.44, 28, 22, 0, Math.PI, 0, Math.PI / 2);
      visorGeo.rotateX(Math.PI / 2);
      visorGeo.scale(0.86, 0.78, 1.05);
      this.faceVisor = new THREE.Mesh(visorGeo, visorDark);
      this.faceVisor.position.set(0, 0.04, 0.08);
      this.headGroup.add(this.faceVisor);

      // Expressive Glowing Digital Visor Eyes
      var eyeGeo = new THREE.CylinderGeometry(0.055, 0.055, 0.17, 18);
      eyeGeo.rotateZ(Math.PI / 2);

      this.eyeLeft = new THREE.Mesh(eyeGeo, glowEyes);
      this.eyeLeft.position.set(-0.15, 0.06, 0.50);
      this.headGroup.add(this.eyeLeft);

      this.eyeRight = new THREE.Mesh(eyeGeo, glowEyes);
      this.eyeRight.position.set(0.15, 0.06, 0.50);
      this.headGroup.add(this.eyeRight);

      // Bright inner pupils for focal intensity
      var pupilGeo = new THREE.SphereGeometry(0.028, 12, 12);
      this.eyePupilLeft = new THREE.Mesh(pupilGeo, glowPupil);
      this.eyePupilLeft.position.set(-0.15, 0.06, 0.53);
      this.headGroup.add(this.eyePupilLeft);

      this.eyePupilRight = new THREE.Mesh(pupilGeo, glowPupil);
      this.eyePupilRight.position.set(0.15, 0.06, 0.53);
      this.headGroup.add(this.eyePupilRight);

      // Visor HUD scan reticle line
      var hudLineGeo = new THREE.BoxGeometry(0.46, 0.012, 0.02);
      var hudLine = new THREE.Mesh(hudLineGeo, glowCyan);
      hudLine.position.set(0, -0.06, 0.51);
      this.headGroup.add(hudLine);

      // Lateral Cyber Audio/Neural Sensor Pods & Antennae
      for (var s = -1; s <= 1; s += 2) {
        var earPodGeo = new THREE.CylinderGeometry(0.15, 0.15, 0.13, 20);
        earPodGeo.rotateZ(Math.PI / 2);
        var earPod = new THREE.Mesh(earPodGeo, chromeTrim);
        earPod.position.set(s * 0.48, 0.05, 0);
        this.headGroup.add(earPod);

        var earRingGeo = new THREE.TorusGeometry(0.11, 0.02, 12, 24);
        earRingGeo.rotateY(Math.PI / 2);
        var earRing = new THREE.Mesh(earRingGeo, glowCyan);
        earRing.position.set(s * 0.54, 0.05, 0);
        this.headGroup.add(earRing);

        var antennaGeo = new THREE.CylinderGeometry(0.015, 0.022, 0.38, 8);
        var antenna = new THREE.Mesh(antennaGeo, chromeTrim);
        antenna.position.set(s * 0.48, 0.30, -0.05);
        antenna.rotation.z = -s * 0.28;
        this.headGroup.add(antenna);

        var tipGeo = new THREE.SphereGeometry(0.04, 12, 12);
        var tip = new THREE.Mesh(tipGeo, glowCyan);
        tip.position.set(s * 0.56, 0.47, -0.05);
        this.headGroup.add(tip);
      }

      // ──────────────────────────────────────────
      // 3. ARTICULATED SHOULDERS & ARMS
      // ──────────────────────────────────────────
      this.leftArm = this._createArm(-1, armorWhite, chromeTrim, glowCyan);
      this.rightArm = this._createArm(1, armorWhite, chromeTrim, glowCyan);
      this.bodyGroup.add(this.leftArm);
      this.bodyGroup.add(this.rightArm);

      // ──────────────────────────────────────────
      // 3.5 ARTICULATED LEGS (walk cycle)
      // ──────────────────────────────────────────
      this.leftLeg = this._createLeg(-1, armorTitanium, armorWhite, chromeTrim, glowCyan);
      this.rightLeg = this._createLeg(1, armorTitanium, armorWhite, chromeTrim, glowCyan);
      this.bodyGroup.add(this.leftLeg);
      this.bodyGroup.add(this.rightLeg);

      // ──────────────────────────────────────────
      // 4. ANTI-GRAVITY PROPULSION POD
      // ──────────────────────────────────────────
      var thrusterGroup = new THREE.Group();
      thrusterGroup.position.set(0, -0.60, 0);
      this.bodyGroup.add(thrusterGroup);

      // Waist magnetic separator ring
      var waistGeo = new THREE.TorusGeometry(0.40, 0.055, 14, 36);
      waistGeo.rotateX(Math.PI / 2);
      var waist = new THREE.Mesh(waistGeo, chromeTrim);
      thrusterGroup.add(waist);

      // Sleek tapered pod cone
      var podGeo = new THREE.ConeGeometry(0.38, 0.70, 22);
      podGeo.rotateX(Math.PI);
      var pod = new THREE.Mesh(podGeo, armorWhite);
      pod.position.set(0, -0.30, 0);
      thrusterGroup.add(pod);

      // Concentric Ion Glow Rings
      var ring1Geo = new THREE.TorusGeometry(0.30, 0.028, 12, 32);
      ring1Geo.rotateX(Math.PI / 2);
      var ring1 = new THREE.Mesh(ring1Geo, glowCyan);
      ring1.position.set(0, -0.16, 0);
      thrusterGroup.add(ring1);
      this.thrusterRings.push(ring1);

      var ring2Geo = new THREE.TorusGeometry(0.20, 0.028, 12, 32);
      ring2Geo.rotateX(Math.PI / 2);
      var ring2 = new THREE.Mesh(ring2Geo, glowCyan);
      ring2.position.set(0, -0.40, 0);
      thrusterGroup.add(ring2);
      this.thrusterRings.push(ring2);

      // Nozzle exhaust emitter & energetic jet plume
      var nozzleGeo = new THREE.SphereGeometry(0.14, 18, 18);
      var nozzle = new THREE.Mesh(nozzleGeo, glowCyan);
      nozzle.position.set(0, -0.64, 0);
      thrusterGroup.add(nozzle);

      var jetGeo = new THREE.ConeGeometry(0.18, 0.65, 16);
      jetGeo.rotateX(Math.PI);
      var jetMat = new THREE.MeshBasicMaterial({
        color: 0x06b6d4,
        transparent: true,
        opacity: 0.65,
        blending: THREE.AdditiveBlending
      });
      this.thrusterJet = new THREE.Mesh(jetGeo, jetMat);
      this.thrusterJet.position.set(0, -0.95, 0);
      thrusterGroup.add(this.thrusterJet);
    },

    /**
     * Builds articulated robotic arm with floating data energy
     */
    _createArm: function(side, armorMat, metalMat, glowMat) {
      var THREE = window.THREE;
      var armGroup = new THREE.Group();
      armGroup.position.set(side * 0.68, 0.44, 0);

      // Spherical Shoulder Joint
      var shoulderJoint = new THREE.Mesh(new THREE.SphereGeometry(0.19, 18, 18), metalMat);
      armGroup.add(shoulderJoint);

      // Shoulder Armor Pauldron Cap
      var capGeo = new THREE.SphereGeometry(0.24, 18, 18, 0, Math.PI * 2, 0, Math.PI / 2);
      var cap = new THREE.Mesh(capGeo, armorMat);
      cap.position.set(0, 0.04, 0);
      armGroup.add(cap);

      // Pauldron glowing plasma node
      var nodeGeo = new THREE.SphereGeometry(0.06, 12, 12);
      var node = new THREE.Mesh(nodeGeo, glowMat);
      node.position.set(side * 0.16, 0.06, 0);
      armGroup.add(node);

      // Upper arm limb
      var bicepGeo = new THREE.CylinderGeometry(0.12, 0.10, 0.46, 16);
      var bicep = new THREE.Mesh(bicepGeo, armorMat);
      bicep.position.set(side * 0.06, -0.28, 0);
      bicep.rotation.z = -side * 0.15;
      armGroup.add(bicep);

      // Elbow joint
      var elbow = new THREE.Mesh(new THREE.SphereGeometry(0.12, 16, 16), metalMat);
      elbow.position.set(side * 0.12, -0.52, 0.02);
      armGroup.add(elbow);

      // Forearm reaching forward/inward to manipulate holograms
      var forearmGeo = new THREE.CylinderGeometry(0.13, 0.10, 0.50, 16);
      var forearm = new THREE.Mesh(forearmGeo, armorMat);
      forearm.position.set(side * 0.18, -0.72, 0.24);
      forearm.rotation.x = Math.PI / 3.6;
      forearm.rotation.z = -side * 0.35;
      armGroup.add(forearm);

      // Glowing circuit strip on forearm
      var stripGeo = new THREE.BoxGeometry(0.035, 0.42, 0.035);
      var strip = new THREE.Mesh(stripGeo, glowMat);
      strip.position.set(side * 0.24, -0.72, 0.24);
      strip.rotation.x = Math.PI / 3.6;
      strip.rotation.z = -side * 0.35;
      armGroup.add(strip);

      // Cyber Hand with Articulated Fingers
      var handGroup = new THREE.Group();
      handGroup.position.set(side * 0.26, -0.88, 0.45);
      armGroup.add(handGroup);
      if (side === -1) this.leftHand = handGroup;
      else this.rightHand = handGroup;

      var palm = new THREE.Mesh(new THREE.BoxGeometry(0.13, 0.07, 0.15), metalMat);
      handGroup.add(palm);

      // Glowing data sphere held in palm
      var dataOrb = new THREE.Mesh(new THREE.SphereGeometry(0.09, 18, 18), glowMat);
      dataOrb.position.set(0, 0.09, 0);
      handGroup.add(dataOrb);

      // Micro glowing emitter ring in palm
      var emitterGeo = new THREE.TorusGeometry(0.06, 0.015, 8, 20);
      emitterGeo.rotateX(Math.PI / 2);
      var emitter = new THREE.Mesh(emitterGeo, glowMat);
      emitter.position.set(0, 0.04, 0);
      handGroup.add(emitter);

      for (var f = -1; f <= 1; f++) {
        var fingerGeo = new THREE.CylinderGeometry(0.019, 0.016, 0.15, 8);
        var finger = new THREE.Mesh(fingerGeo, metalMat);
        finger.position.set(f * 0.045, 0.07, 0.09);
        finger.rotation.x = -Math.PI / 4.2;
        handGroup.add(finger);
      }

      return armGroup;
    },

    /**
     * Builds one articulated robot leg with hip, knee and ankle joints.
     * side: -1 (left) or 1 (right).
     */
    _createLeg: function(side, titaniumMat, whiteMat, metalMat, glowMat) {
      var THREE = window.THREE;
      var legGroup = new THREE.Group();
      legGroup.position.set(side * 0.22, -0.06, 0);

      // Hip joint
      var hip = new THREE.Mesh(new THREE.SphereGeometry(0.15, 14, 14), metalMat);
      legGroup.add(hip);

      // Thigh
      var thighGeo = new THREE.CylinderGeometry(0.13, 0.10, 0.52, 14);
      var thigh = new THREE.Mesh(thighGeo, titaniumMat);
      thigh.position.set(side * 0.04, -0.34, 0);
      thigh.rotation.z = side * 0.10;
      legGroup.add(thigh);

      // Knee
      var knee = new THREE.Mesh(new THREE.SphereGeometry(0.11, 12, 12), metalMat);
      knee.position.set(side * 0.08, -0.66, 0.02);
      legGroup.add(knee);

      // Shin
      var shinGeo = new THREE.CylinderGeometry(0.10, 0.08, 0.50, 14);
      var shin = new THREE.Mesh(shinGeo, titaniumMat);
      shin.position.set(side * 0.10, -0.96, 0.10);
      shin.rotation.z = -side * 0.12;
      legGroup.add(shin);

      // Ankle / foot
      var footGeo = new THREE.BoxGeometry(0.26, 0.10, 0.42);
      var foot = new THREE.Mesh(footGeo, whiteMat);
      foot.position.set(side * 0.12, -1.24, 0.14);
      foot.rotation.x = -0.10;
      legGroup.add(foot);

      // Foot thruster glow (hover assist)
      var footGlow = new THREE.Mesh(new THREE.SphereGeometry(0.05, 10, 10), glowMat);
      footGlow.position.set(side * 0.12, -1.30, 0.28);
      legGroup.add(footGlow);

      legGroup.userData = { side: side, baseY: -0.06, knee: knee, thigh: thigh, shin: shin, foot: foot };
      return legGroup;
    },

    /**
     * Builds surrounding holographic wireframes, celestial gyro rings, and ground radar
     */
    _buildHolographicScene: function() {
      var THREE = window.THREE;
      this.holoGroup = new THREE.Group();
      this.scene.add(this.holoGroup);

      // ──────────────────────────────────────────
      // 1. GROUND RADAR PROJECTION FLOOR
      // ──────────────────────────────────────────
      var radarGeo = new THREE.RingGeometry(0.8, 2.9, 48);
      radarGeo.rotateX(-Math.PI / 2);
      var radarMat = new THREE.MeshBasicMaterial({
        color: 0x06b6d4,
        transparent: true,
        opacity: 0.28,
        wireframe: true,
        side: THREE.DoubleSide
      });
      this.groundRadar = new THREE.Mesh(radarGeo, radarMat);
      this.groundRadar.position.set(0, -1.8, 0);
      this.holoGroup.add(this.groundRadar);

      // ──────────────────────────────────────────
      // 2. CELESTIAL GYROSCOPIC GIMBAL RINGS
      // ──────────────────────────────────────────
      var gyro1Geo = new THREE.TorusGeometry(2.7, 0.024, 12, 64);
      var gyro1Mat = new THREE.MeshBasicMaterial({ color: 0x06b6d4, transparent: true, opacity: 0.45 });
      this.gyroRing1 = new THREE.Mesh(gyro1Geo, gyro1Mat);
      this.gyroRing1.rotation.x = Math.PI / 3;
      this.holoGroup.add(this.gyroRing1);

      // Orbiting satellite node on Gyro 1
      this.satellite1 = new THREE.Mesh(new THREE.SphereGeometry(0.08, 12, 12), new THREE.MeshBasicMaterial({ color: 0x38bdf8 }));
      this.satellite1.position.set(2.7, 0, 0);
      this.gyroRing1.add(this.satellite1);

      var gyro2Geo = new THREE.TorusGeometry(2.5, 0.020, 12, 64);
      var gyro2Mat = new THREE.MeshBasicMaterial({ color: 0xa855f7, transparent: true, opacity: 0.45 });
      this.gyroRing2 = new THREE.Mesh(gyro2Geo, gyro2Mat);
      this.gyroRing2.rotation.y = Math.PI / 4;
      this.gyroRing2.rotation.z = Math.PI / 6;
      this.holoGroup.add(this.gyroRing2);

      // Orbiting satellite node on Gyro 2
      this.satellite2 = new THREE.Mesh(new THREE.SphereGeometry(0.07, 12, 12), new THREE.MeshBasicMaterial({ color: 0xc084fc }));
      this.satellite2.position.set(0, 2.5, 0);
      this.gyroRing2.add(this.satellite2);

      // ──────────────────────────────────────────
      // 3. 4 FLOATING HOLOGRAPHIC BLUEPRINT PANELS
      // ──────────────────────────────────────────
      var panelConfigs = [
        { type: 'hero', label: 'NEURAL HERO & NAV BLUEPRINT', angle: 0, y: 0.40, radius: 2.30 },
        { type: 'bento', label: 'DYNAMIC COMPONENT ARCHITECTURE', angle: (Math.PI * 2) / 4, y: 0.15, radius: 2.35 },
        { type: 'mobile', label: 'RESPONSIVE VIEWPORT ENGINE', angle: (Math.PI * 4) / 4, y: -0.10, radius: 2.25 },
        { type: 'code', label: 'AUTONOMOUS CODE SYNTHESIS', angle: (Math.PI * 6) / 4, y: 0.25, radius: 2.30 }
      ];

      var self = this;
      panelConfigs.forEach(function(cfg, idx) {
        var panelTex = self._createHoloTexture(cfg.type, cfg.label, 'Zenith Smart Platform');
        var panelGeo = new THREE.PlaneGeometry(1.45, 0.94);
        var panelMat = new THREE.MeshBasicMaterial({
          map: panelTex,
          transparent: true,
          opacity: 0.88,
          side: THREE.DoubleSide
        });
        var panelMesh = new THREE.Mesh(panelGeo, panelMat);
        panelMesh.position.set(
          Math.cos(cfg.angle) * cfg.radius,
          cfg.y,
          Math.sin(cfg.angle) * cfg.radius
        );
        panelMesh.lookAt(0, cfg.y, 0); // Facing the robot core

        // Add glowing 3D frame wireframe box around each card
        var frameGeo = new THREE.EdgesGeometry(panelGeo);
        var frameMat = new THREE.LineBasicMaterial({ color: idx % 2 === 0 ? 0x06b6d4 : 0xa855f7, transparent: true, opacity: 0.7 });
        var frame = new THREE.LineSegments(frameGeo, frameMat);
        panelMesh.add(frame);

        panelMesh.userData = {
          angle: cfg.angle,
          radius: cfg.radius,
          baseRadius: cfg.radius,
          baseY: cfg.y,
          speed: 0.28,
          index: idx
        };
        self.holoGroup.add(panelMesh);
        self.wireframePanels.push(panelMesh);

        // ──────────────────────────────────────────
        // 4. DYNAMIC ENERGY SYNTHESIS LASER BEAMS
        // ──────────────────────────────────────────
        // A glowing beam connecting the robot's palms/reactor to the center of each panel!
        var beamGeo = new THREE.BufferGeometry();
        var beamPositions = new Float32Array(6); // 2 vertices (x1, y1, z1, x2, y2, z2)
        beamGeo.setAttribute('position', new THREE.BufferAttribute(beamPositions, 3));
        var beamMat = new THREE.LineBasicMaterial({
          color: idx % 2 === 0 ? 0x38bdf8 : 0xc084fc,
          transparent: true,
          opacity: 0.75,
          blending: THREE.AdditiveBlending,
          linewidth: 2
        });
        var beamLine = new THREE.Line(beamGeo, beamMat);
        beamLine.userData = { targetPanel: panelMesh, side: idx % 2 === 0 ? -1 : 1 };
        self.scene.add(beamLine);
        self.laserBeams.push(beamLine);
      });

      // ──────────────────────────────────────────
      // 5. FLOATING REQUIREMENT CHIPS
      // ──────────────────────────────────────────
      this._buildRequirementChips([
        'Business Idea',
        'Images & Media',
        'Layout Structure',
        'Features & Functions',
        'Colors & Style',
        'Custom Website for Your Business'
      ]);

      // ──────────────────────────────────────────
      // 6. VERTICAL LASER SCANNING BEAM
      // ──────────────────────────────────────────
      var scanGeo = new THREE.PlaneGeometry(4.2, 0.05);
      scanGeo.rotateX(Math.PI / 2);
      var scanMat = new THREE.MeshBasicMaterial({
        color: 0x38bdf8,
        transparent: true,
        opacity: 0.78,
        side: THREE.DoubleSide
      });
      this.scannerBeam = new THREE.Mesh(scanGeo, scanMat);
      this.scannerBeam.position.set(0, 0.2, 0);
      this.holoGroup.add(this.scannerBeam);
    },

    /**
     * Builds or updates the floating requirement chips
     */
    _buildRequirementChips: function(chips) {
      var THREE = window.THREE;
      var self = this;

      // Remove existing chips if any
      this.chipMeshes.forEach(function(mesh) {
        if (self.holoGroup) self.holoGroup.remove(mesh);
        if (mesh.geometry) mesh.geometry.dispose();
        if (mesh.material && mesh.material.map) mesh.material.map.dispose();
      });
      this.chipMeshes = [];

      chips.forEach(function(text, idx) {
        var cv = document.createElement('canvas');
        cv.width = 300;
        cv.height = 72;
        var ctx = cv.getContext('2d');
        ctx.fillStyle = 'rgba(8, 15, 32, 0.90)';
        ctx.fillRect(0, 0, cv.width, cv.height);
        ctx.strokeStyle = idx % 2 === 0 ? '#38bdf8' : '#c084fc';
        ctx.lineWidth = 3;
        ctx.strokeRect(3, 3, cv.width - 6, cv.height - 6);
        ctx.fillStyle = '#67e8f9';
        ctx.font = 'bold 15px "Fira Code", monospace';
        ctx.textAlign = 'center';
        ctx.fillText(text, cv.width / 2, 42);

        var chipTex = new THREE.CanvasTexture(cv);
        var chipGeo = new THREE.PlaneGeometry(0.96, 0.25);
        var chipMat = new THREE.MeshBasicMaterial({
          map: chipTex,
          transparent: true,
          opacity: 0.90,
          side: THREE.DoubleSide
        });
        var chipMesh = new THREE.Mesh(chipGeo, chipMat);

        var a = (idx / chips.length) * Math.PI * 2;
        chipMesh.position.set(Math.cos(a) * 1.62, 0.92 + (idx % 2) * 0.26, Math.sin(a) * 1.62);
        chipMesh.userData = { angle: a, radius: 1.62, baseY: chipMesh.position.y, speed: 0.20 };
        self.holoGroup.add(chipMesh);
        self.chipMeshes.push(chipMesh);
      });
    },

    /**
     * Builds the holographic website assembly that forms in front of the robot.
     * Components start hidden/scattered and snap into a complete layout during
     * the "assembling" story act.
     */
    _buildWebsiteAssembly: function() {
      var THREE = window.THREE;
      this.websiteRoot = new THREE.Group();
      this.websiteRoot.position.set(0, -0.05, 2.6);
      this.websiteRoot.visible = false;
      this.scene.add(this.websiteRoot);

      var glass = new THREE.MeshBasicMaterial({
        color: 0xffffff, transparent: true, opacity: 0.72, side: THREE.DoubleSide
      });
      var glowCyan = new THREE.MeshBasicMaterial({ color: 0x22d3ee, transparent: true, opacity: 0.9 });
      var glowViolet = new THREE.MeshBasicMaterial({ color: 0xa855f7, transparent: true, opacity: 0.85 });
      var darkPanel = new THREE.MeshBasicMaterial({
        color: 0x050b1d, transparent: true, opacity: 0.85, side: THREE.DoubleSide
      });

      var self = this;
      function addPiece(geo, mat, pos, rot, scale, delay) {
        var mesh = new THREE.Mesh(geo, mat);
        if (pos) mesh.position.copy(pos);
        if (rot) mesh.rotation.copy(rot);
        if (scale) mesh.scale.set(scale, scale, scale);
        mesh.visible = false;
        self.websiteRoot.add(mesh);
        self.websiteComponents.push({
          mesh: mesh,
          targetPos: pos ? pos.clone() : new THREE.Vector3(),
          targetRot: rot ? rot.clone() : new THREE.Euler(),
          targetScale: scale || 1,
          active: false,
          delay: delay || 0,
          started: false
        });
        return mesh;
      }

      var w = 2.4, h = 1.5;

      // Navigation bar (top)
      addPiece(
        new THREE.PlaneGeometry(w * 0.92, h * 0.16),
        darkPanel,
        new THREE.Vector3(0, h * 0.42, 0),
        null, 1, 0
      );
      // Nav links (3 small squares)
      for (var i = 0; i < 3; i++) {
        addPiece(
          new THREE.PlaneGeometry(0.14, 0.14),
          glowCyan,
          new THREE.Vector3(-0.55 + i * 0.55, h * 0.42, 0.02),
          null, 1, 0.15 + i * 0.05
        );
      }

      // Hero section (large panel)
      addPiece(
        new THREE.PlaneGeometry(w, h * 0.62),
        glass,
        new THREE.Vector3(0, h * 0.05, 0),
        null, 1, 0.4
      );
      // Hero title bars
      addPiece(new THREE.PlaneGeometry(w * 0.5, 0.06), glowCyan, new THREE.Vector3(-0.6, h * 0.34, 0.02), null, 1, 0.5);
      addPiece(new THREE.PlaneGeometry(w * 0.3, 0.05), glowViolet, new THREE.Vector3(-0.6, h * 0.24, 0.02), null, 1, 0.55);
      // Hero CTA button
      addPiece(new THREE.PlaneGeometry(0.5, 0.14), glowCyan, new THREE.Vector3(0.55, -0.12, 0.02), null, 1, 0.6);

      // Image / media card (right)
      addPiece(
        new THREE.PlaneGeometry(w * 0.42, h * 0.42),
        darkPanel,
        new THREE.Vector3(w * 0.38, -h * 0.14, 0),
        null, 1, 0.7
      );
      addPiece(new THREE.PlaneGeometry(w * 0.38, 0.05), glowCyan, new THREE.Vector3(w * 0.38, -h * 0.14, 0.02), null, 1, 0.75);

      // Service cards row (3 cards at bottom)
      for (var i = 0; i < 3; i++) {
        addPiece(
          new THREE.PlaneGeometry(w * 0.26, h * 0.30),
          glass,
          new THREE.Vector3(-w * 0.32 + i * w * 0.32, -h * 0.44, 0),
          null, 1, 0.85 + i * 0.06
        );
        addPiece(new THREE.PlaneGeometry(w * 0.22, 0.05), glowViolet, new THREE.Vector3(-w * 0.32 + i * w * 0.32, -h * 0.22, 0.02), null, 1, 0.9 + i * 0.06);
      }

      // Footer strip
      addPiece(
        new THREE.PlaneGeometry(w * 0.92, 0.14),
        darkPanel,
        new THREE.Vector3(0, -h * 0.66, 0),
        null, 1, 1.0
      );
    },

    /**
     * Builds double-layer ambient cyber particles and upward thruster plume particles
     */
    _buildParticleClouds: function() {
      var THREE = window.THREE;

      // 1. Ambient Stardust Particles (140 points)
      var count = 140;
      var pos = new Float32Array(count * 3);
      var col = new Float32Array(count * 3);

      var colorOptions = [
        new THREE.Color(0x38bdf8),
        new THREE.Color(0x818cf8),
        new THREE.Color(0x34d399),
        new THREE.Color(0xa855f7),
        new THREE.Color(0xf59e0b)
      ];

      for (var i = 0; i < count; i++) {
        var rad = 1.3 + Math.random() * 2.5;
        var theta = Math.random() * Math.PI * 2;
        var phi = (Math.random() - 0.5) * Math.PI;

        pos[i * 3] = rad * Math.cos(theta) * Math.cos(phi);
        pos[i * 3 + 1] = rad * Math.sin(phi) + 0.15;
        pos[i * 3 + 2] = rad * Math.sin(theta) * Math.cos(phi);

        var c = colorOptions[Math.floor(Math.random() * colorOptions.length)];
        col[i * 3] = c.r;
        col[i * 3 + 1] = c.g;
        col[i * 3 + 2] = c.b;
      }

      var pGeo = new THREE.BufferGeometry();
      pGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
      pGeo.setAttribute('color', new THREE.BufferAttribute(col, 3));

      var pMat = new THREE.PointsMaterial({
        size: 0.055,
        vertexColors: true,
        transparent: true,
        opacity: 0.85,
        blending: THREE.AdditiveBlending
      });

      this.ambientParticles = new THREE.Points(pGeo, pMat);
      this.scene.add(this.ambientParticles);

      // 2. Upward Thruster Spiral Ion Particles (60 points)
      var tCount = 60;
      var tPos = new Float32Array(tCount * 3);
      var tCol = new Float32Array(tCount * 3);
      for (var j = 0; j < tCount; j++) {
        var angle = (j / tCount) * Math.PI * 4;
        var r = 0.15 + (j / tCount) * 0.45;
        var y = -0.7 - (j / tCount) * 1.1;
        tPos[j * 3] = Math.cos(angle) * r;
        tPos[j * 3 + 1] = y;
        tPos[j * 3 + 2] = Math.sin(angle) * r;

        tCol[j * 3] = 0.13;
        tCol[j * 3 + 1] = 0.82;
        tCol[j * 3 + 2] = 0.93;
      }
      var tGeo = new THREE.BufferGeometry();
      tGeo.setAttribute('position', new THREE.BufferAttribute(tPos, 3));
      tGeo.setAttribute('color', new THREE.BufferAttribute(tCol, 3));
      var tMat = new THREE.PointsMaterial({
        size: 0.065,
        vertexColors: true,
        transparent: true,
        opacity: 0.75,
        blending: THREE.AdditiveBlending
      });
      this.thrusterParticles = new THREE.Points(tGeo, tMat);
      this.scene.add(this.thrusterParticles);
    },

    /**
     * Builds the particle system used when requirement cards are collected:
     * particles flow from the caught card into the robot's core.
     */
    _buildCollectionParticles: function() {
      var THREE = window.THREE;
      var count = 60;
      var pos = new Float32Array(count * 3);
      var col = new Float32Array(count * 3);
      for (var i = 0; i < count; i++) {
        pos[i * 3] = 0;
        pos[i * 3 + 1] = 0;
        pos[i * 3 + 2] = 0;
        col[i * 3] = 0.13;
        col[i * 3 + 1] = 0.82;
        col[i * 3 + 2] = 0.93;
      }
      var geo = new THREE.BufferGeometry();
      geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
      geo.setAttribute('color', new THREE.BufferAttribute(col, 3));
      var mat = new THREE.PointsMaterial({
        size: 0.07,
        vertexColors: true,
        transparent: true,
        opacity: 0.9,
        blending: THREE.AdditiveBlending
      });
      this.collectedParticles = new THREE.Points(geo, mat);
      this.collectedParticles.visible = false;
      this.scene.add(this.collectedParticles);

      for (var i = 0; i < count; i++) {
        this.particlePool.push({
          active: false,
          x: 0, y: 0, z: 0,
          tx: 0, ty: 0, tz: 0,
          speed: 0,
          life: 0,
          index: i
        });
      }
    },

    /**
     * Spawns a burst of collection particles from a card position toward the core.
     */
    _emitCollectionBurst: function(sx, sy, sz) {
      if (!this.collectedParticles) return;
      var corePos = new THREE.Vector3(0, 0.12, 0.50);
      if (this.coreMesh) {
        this.coreMesh.getWorldPosition(corePos);
      }
      var pool = this.particlePool;
      var spawned = 0;
      for (var i = 0; i < pool.length && spawned < 18; i++) {
        var p = pool[i];
        if (p.active) continue;
        p.active = true;
        p.x = sx; p.y = sy; p.z = sz;
        p.tx = corePos.x + (Math.random() - 0.5) * 0.15;
        p.ty = corePos.y + (Math.random() - 0.5) * 0.15;
        p.tz = corePos.z + (Math.random() - 0.5) * 0.15;
        p.speed = 0.06 + Math.random() * 0.09;
        p.life = 1.0;
        spawned++;
      }
      if (!this.collectedParticles.visible) this.collectedParticles.visible = true;
    },

    /**
     * Window / Container resize handler
     */
    _handleResize: function() {
      if (!this.container || !this.renderer || !this.camera) return;
      var w = this.container.clientWidth || window.innerWidth;
      var h = this.container.clientHeight || window.innerHeight;
      this.camera.aspect = w / h;

      // Adjust orbital camera distance for mobile responsiveness
      if (w < 600) {
        this.cameraRadius = 9.4;
        this.cameraHeight = 0.45;
      } else if (w < 900) {
        this.cameraRadius = 8.4;
        this.cameraHeight = 0.4;
      } else {
        this.cameraRadius = 7.2;
        this.cameraHeight = 0.6;
      }

      this.camera.updateProjectionMatrix();
      this.renderer.setSize(w, h, false);
    },

    /**
     * Start animation loop on generation start
     */
    start: function(data) {
      this.businessData = data || null;
      if (!this.isInitialized) {
        var ok = this.init();
        if (!ok) {
          // WebGL / Three.js unavailable: emphasize the CSS-based robot fallback
          try {
            var ov = document.getElementById('gen-overlay');
            if (ov) ov.classList.add('wc-robot-3d-fallback');
          } catch (e) {}
          if (this.container) {
            this.container.style.display = 'none';
          }
          return;
        }
      }

      // Customer color palette reactivity
      if (data && data.color_palette) {
        var p = String(data.color_palette).toLowerCase();
        var hexMap = {
          purple: 0x8b5cf6,
          blue: 0x06b6d4,
          green: 0x10b981,
          red: 0xf43f5e,
          gold: 0xf59e0b,
          slate: 0x38bdf8
        };
        this.brandColor = hexMap[p] || 0x22d3ee;
        if (this.coreLight) this.coreLight.color.setHex(this.brandColor);
        if (this.coreMesh) this.coreMesh.material.color.setHex(this.brandColor);
        if (this.haloRing) this.haloRing.material.color.setHex(this.brandColor);
        this.thrusterRings.forEach(function(r) { r.material.color.setHex(WCRobot3D.brandColor); });
      }

      // Dynamic Requirement Chips & Panels with real customer brief
      if (data && (data.biz_name || data.biz_type)) {
        var namePart = (data.biz_name || 'BUSINESS').slice(0, 18).toUpperCase();
        var typePart = (data.biz_type || 'LAYOUT').slice(0, 18).toUpperCase();
        var palPart = (data.color_palette || 'CYBER').toUpperCase();
        this._buildRequirementChips([
          'Business Idea: ' + namePart,
          'Images & Media',
          'Layout Structure: ' + typePart,
          'Features & Functions',
          'Colors & Style: ' + palPart,
          'Custom Website for Your Business'
        ]);

        // Re-generate texture for Code panel with real customer brief
        if (this.wireframePanels && this.wireframePanels[3]) {
          var updatedTex = this._createHoloTexture('code', 'AUTONOMOUS CODE SYNTHESIS', namePart + ' / ' + typePart);
          this.wireframePanels[3].material.map = updatedTex;
          this.wireframePanels[3].material.needsUpdate = true;
        }
      }

      this.currentStage = 0;
      this.stageProgress = 0;
      this.isRunning = true;
      if (this.clock) this.clock.start();

      // Reset story animation state
      this.storyAct = 'walking';
      this.storyTime = 0;
      this.walkCycle = 0;
      this.cardsCaught = 0;
      this.totalCards = this.chipMeshes ? this.chipMeshes.length : 4;
      this.reachTarget = null;
      this.caughtCards = [];
      if (this.websiteRoot) this.websiteRoot.visible = false;
      if (this.websiteComponents) {
        this.websiteComponents.forEach(function(c) {
          c.active = false; c.started = false; c.mesh.visible = false;
        });
      }
      if (this.collectedParticles) this.collectedParticles.visible = false;
      this.particlePool.forEach(function(p) { p.active = false; });
      this.cameraOrbitAngle = 0;

      // Ensure canvas wrapper is visible
      if (this.container) {
        this.container.style.opacity = '1';
        this.container.style.display = 'block';
      }

      this._handleResize();

      if (!this.animId) {
        this._animate();
      }
    },

    /**
     * Main animation frame loop (smooth 60fps)
     */
    _animate: function() {
      var self = WCRobot3D;
      if (!self.isRunning) {
        self.animId = null;
        return;
      }

      self.animId = requestAnimationFrame(self._animate);
      self._updateScene();
    },

    /**
     * Updates 3D transforms, 360° rotation, dynamic lasers, and hover dynamics
     */
    _updateScene: function() {
      var delta = this.clock ? this.clock.getDelta() : 0.016;
      var time = this.clock ? this.clock.getElapsedTime() : Date.now() * 0.001;

      // Advance story clock (seconds inside the current act)
      this.storyTime += delta;

      // ──────────────────────────────────────────
      // 0. ORBITAL CAMERA — smooth 360° around the scene
      //    Subtle mouse parallax is layered on top so the orbit stays readable.
      // ──────────────────────────────────────────
      var orbitSpeed = this.reducedMotion ? 0.06 : 0.22;
      this.cameraOrbitAngle += orbitSpeed * delta;
      var mx = this.currentMouseX * 0.5;
      var my = this.currentMouseY * 0.35;
      if (this.camera) {
        this.camera.position.x = Math.sin(this.cameraOrbitAngle) * this.cameraRadius + mx;
        this.camera.position.y = this.cameraHeight + my;
        this.camera.position.z = Math.cos(this.cameraOrbitAngle) * this.cameraRadius;
        this.camera.lookAt(0, 0.05, 0);
      }

      // Keep mouse parallax values fresh (used by story targeting)
      this.currentMouseX += (this.mouseTargetX - this.currentMouseX) * 0.06;
      this.currentMouseY += (this.mouseTargetY - this.currentMouseY) * 0.06;

      // ──────────────────────────────────────────
      // 1. ROBOT ROTATION + STORY DRIVEN MOVEMENT
      // ──────────────────────────────────────────
      if (this.robotRoot) {
        // Slow continuous rotation so the full robot is visible from all angles
        var rotSpeed = this.reducedMotion ? 0.06 : 0.28;
        this.robotRoot.rotation.y += rotSpeed * delta;

        // Story-driven translation (robot walks toward the requirement cards)
        var moveX = 0, moveZ = 0;
        if (this.storyAct === 'walking') {
          // Walk from front-left toward centre
          var t = Math.min(1, this.storyTime / 2.2);
          moveX = -1.6 + (1.6 * t);
          moveZ = 1.0 - (1.0 * t);
        } else if (this.storyAct === 'reaching' || this.storyAct === 'catching') {
          moveX = 0; moveZ = 0;
        } else if (this.storyAct === 'collecting' || this.storyAct === 'assembling') {
          moveX = 0; moveZ = 0;
        } else if (this.storyAct === 'complete') {
          moveX = 0; moveZ = 0;
        }
        this.robotRoot.position.x = moveX;
        this.robotRoot.position.z = moveZ;

        // Subtle floating bobbing
        var hoverAmp = this.reducedMotion ? 0.04 : 0.10;
        this.robotRoot.position.y = this.baseHoverY + Math.sin(time * 1.8) * hoverAmp;
      }

      // ── Walk cycle (legs) ──
      if ((this.storyAct === 'walking') && this.leftLeg && this.rightLeg) {
        this.walkCycle += delta * 2.6;
        var wAmp = this.reducedMotion ? 0.10 : 0.28;
        var wSpeed = this.walkCycle;
        var leftKnee = this.leftLeg.userData.knee;
        var leftThigh = this.leftLeg.userData.thigh;
        var leftShin = this.leftLeg.userData.shin;
        var leftFoot = this.leftLeg.userData.foot;
        var rightKnee = this.rightLeg.userData.knee;
        var rightThigh = this.rightLeg.userData.thigh;
        var rightShin = this.rightLeg.userData.shin;
        var rightFoot = this.rightLeg.userData.foot;

        var lSwing = Math.sin(wSpeed) * wAmp;
        var rSwing = Math.sin(wSpeed + Math.PI) * wAmp;
        var lKneeBend = Math.max(0, Math.sin(wSpeed)) * 0.9;
        var rKneeBend = Math.max(0, Math.sin(wSpeed + Math.PI)) * 0.9;
        var lFootRoll = Math.sin(wSpeed) * 0.15;
        var rFootRoll = Math.sin(wSpeed + Math.PI) * 0.15;

        leftThigh.rotation.z = lSwing * 0.6;
        rightThigh.rotation.z = rSwing * 0.6;
        if (leftKnee) leftKnee.rotation.x = lKneeBend;
        if (rightKnee) rightKnee.rotation.x = rKneeBend;
        if (leftShin) leftShin.rotation.z = -lSwing * 0.4 + lFootRoll;
        if (rightShin) rightShin.rotation.z = -rSwing * 0.4 + rFootRoll;
        if (leftFoot) leftFoot.rotation.x = -0.10 + lFootRoll * 0.5;
        if (rightFoot) rightFoot.rotation.x = -0.10 + rFootRoll * 0.5;

        // Body bob with steps
        if (this.robotRoot) {
          this.robotRoot.position.y = this.baseHoverY + Math.abs(Math.sin(wSpeed)) * 0.06 + Math.sin(time * 1.8) * 0.05;
        }
        // Feet glow pulses with steps
        if (this.leftLeg && this.rightLeg) {
          var glowL = this.leftLeg.children[this.leftLeg.children.length - 1];
          var glowR = this.rightLeg.children[this.rightLeg.children.length - 1];
          if (glowL) glowL.scale.setScalar(0.8 + Math.abs(Math.sin(wSpeed)) * 0.6);
          if (glowR) glowR.scale.setScalar(0.8 + Math.abs(Math.sin(wSpeed + Math.PI)) * 0.6);
        }
      } else if (this.leftLeg && this.rightLeg) {
        // Idle stance: legs straight, slight hover
        var idleBob = Math.sin(time * 1.8) * 0.03;
        var lk = this.leftLeg.userData.knee, rk = this.rightLeg.userData.knee;
        var lt = this.leftLeg.userData.thigh, rt = this.rightLeg.userData.thigh;
        var ls = this.leftLeg.userData.shin, rs = this.rightLeg.userData.shin;
        var lf = this.leftLeg.userData.foot, rf = this.rightLeg.userData.foot;
        if (lt) lt.rotation.z = 0;
        if (rt) rt.rotation.z = 0;
        if (lk) lk.rotation.x = 0;
        if (rk) rk.rotation.x = 0;
        if (ls) ls.rotation.z = 0;
        if (rs) rs.rotation.z = 0;
        if (lf) lf.rotation.x = -0.10;
        if (rf) rf.rotation.x = -0.10;
      }

      // ──────────────────────────────────────────
      // 2. HEAD, HALO, CORE & EYES
      // ──────────────────────────────────────────
      if (this.headGroup) {
        this.headGroup.rotation.y = Math.sin(time * 1.2) * 0.18;
        this.headGroup.rotation.x = -0.05 + Math.sin(time * 1.6) * 0.08;
        this.headGroup.rotation.z = Math.sin(time * 0.9) * 0.04;
      }

      if (this.haloRing) {
        this.haloRing.rotation.z += 0.8 * delta;
        this.haloRing.position.y = 0.65 + Math.sin(time * 2.4) * 0.04;
      }

      if (this.coreTurbine) {
        this.coreTurbine.rotation.z += 2.2 * delta;
      }

      // Core light pulsation — brighter while collecting / assembling
      if (this.coreLight) {
        var baseInt = 2.6;
        if (this.storyAct === 'collecting' || this.storyAct === 'assembling') baseInt = 4.2;
        if (this.storyAct === 'complete') baseInt = 6.0;
        this.coreLight.intensity = baseInt + Math.sin(time * 3.4) * 0.8;
      }

      // Visor eye blinking simulation
      this.blinkTimer += delta;
      if (!this.isBlinking && this.blinkTimer > 3.6 + Math.random() * 2.0) {
        this.isBlinking = true;
        this.blinkTimer = 0;
        if (this.eyeLeft && this.eyeRight) {
          this.eyeLeft.scale.y = 0.12;
          this.eyeRight.scale.y = 0.12;
        }
        if (this.eyePupilLeft && this.eyePupilRight) {
          this.eyePupilLeft.scale.y = 0.12;
          this.eyePupilRight.scale.y = 0.12;
        }
      } else if (this.isBlinking && this.blinkTimer > 0.14) {
        this.isBlinking = false;
        this.blinkTimer = 0;
        if (this.eyeLeft && this.eyeRight) {
          this.eyeLeft.scale.y = 1.0;
          this.eyeRight.scale.y = 1.0;
        }
        if (this.eyePupilLeft && this.eyePupilRight) {
          this.eyePupilLeft.scale.y = 1.0;
          this.eyePupilRight.scale.y = 1.0;
        }
      }

      // ──────────────────────────────────────────
      // 3. ARM GESTURES — story driven (reach vs idle)
      // ──────────────────────────────────────────
      if (this.leftArm && this.rightArm) {
        var reach = 0;
        if (this.storyAct === 'reaching' || this.storyAct === 'catching') {
          reach = Math.min(1, this.storyTime / 1.1) * 1.35;
        }
        // Left arm (side -1): rotate toward centre when reaching
        if (this.storyAct === 'reaching' || this.storyAct === 'catching') {
          this.leftArm.rotation.x = -0.15 + reach * 1.1;
          this.leftArm.rotation.y = -0.25 + reach * 0.55;
          this.leftArm.rotation.z = -0.35 + reach * 0.5;
          this.rightArm.rotation.x = -0.22 + reach * 1.1;
          this.rightArm.rotation.y = 0.25 - reach * 0.55;
          this.rightArm.rotation.z = 0.35 - reach * 0.5;
        } else {
          this.leftArm.rotation.x = -0.15 + Math.sin(time * 1.5) * 0.10;
          this.leftArm.rotation.y = Math.sin(time * 1.1) * 0.08;
          this.rightArm.rotation.x = -0.22 + Math.cos(time * 1.5) * 0.10;
          this.rightArm.rotation.y = -Math.sin(time * 1.1) * 0.08;
        }
      }

      // Thruster concentric rings pulse & jet flame
      if (this.thrusterRings.length) {
        this.thrusterRings[0].scale.setScalar(1.0 + Math.sin(time * 6.5) * 0.07);
        this.thrusterRings[1].scale.setScalar(1.0 + Math.cos(time * 6.5) * 0.07);
      }
      if (this.thrusterJet) {
        this.thrusterJet.scale.y = 1.0 + Math.sin(time * 12.0) * 0.18;
      }

      // ──────────────────────────────────────────
      // 4. CELESTIAL GYROS & GROUND RADAR
      // ──────────────────────────────────────────
      if (this.groundRadar) this.groundRadar.rotation.z += 0.15 * delta;
      if (this.gyroRing1) this.gyroRing1.rotation.z += 0.35 * delta;
      if (this.gyroRing2) this.gyroRing2.rotation.x -= 0.28 * delta;

      // ──────────────────────────────────────────
      // 5. STORY: REQUIREMENT CARDS (chips) — orbit, catch & collect
      // ──────────────────────────────────────────
      var self = this;
      var handWorldPosLeft = new THREE.Vector3();
      var handWorldPosRight = new THREE.Vector3();
      var handWorldPos = new THREE.Vector3();
      if (this.leftHand) this.leftHand.getWorldPosition(handWorldPosLeft);
      if (this.rightHand) this.rightHand.getWorldPosition(handWorldPosRight);
      handWorldPos.copy(handWorldPosLeft).add(handWorldPosRight).multiplyScalar(0.5);

      this.chipMeshes.forEach(function(c, i) {
        if (!c.userData) return;
        var caught = c.userData.caught;
        if (caught) {
          // Card is held in hands: pull toward centre / core
          c.userData.catchT = Math.min(1, (c.userData.catchT || 0) + delta * 2.0);
          var ct = c.userData.catchT;
          c.position.x = c.userData.catchStartX + (handWorldPos.x - c.userData.catchStartX) * ct;
          c.position.y = c.userData.catchStartY + (0.75 - c.userData.catchStartY) * ct;
          c.position.z = c.userData.catchStartZ + (0.55 - c.userData.catchStartZ) * ct;
          c.rotation.z += delta * 2.0;
          c.scale.setScalar(1.0 - ct * 0.7);
          c.material.opacity = 0.9 - ct * 0.5;
        } else {
          // Orbit around robot
          c.userData.angle += c.userData.speed * delta;
          var rad = c.userData.radius;
          var radiusMod = (self.storyAct === 'reaching' || self.storyAct === 'catching') ? 0.7 : 1.0;
          c.position.x = Math.cos(c.userData.angle) * rad * radiusMod;
          c.position.z = Math.sin(c.userData.angle) * rad * radiusMod;
          c.position.y = c.userData.baseY + Math.sin(time * 2.2 + i) * 0.07;
          c.scale.setScalar(1.0);
          c.material.opacity = 0.9;
        }
        if (self.camera) c.lookAt(self.camera.position);
      });

      // Catch a card when within reach during 'catching' act
      if (this.storyAct === 'catching') {
        this.chipMeshes.forEach(function(c, i) {
          if (c.userData.caught || self.cardsCaught >= self.totalCards) return;
          if (!self.reachTarget) return;
          var dist = c.position.distanceTo(self.reachTarget);
          if (dist < 0.9) {
            c.userData.caught = true;
            c.userData.catchT = 0;
            c.userData.catchStartX = c.position.x;
            c.userData.catchStartY = c.position.y;
            c.userData.catchStartZ = c.position.z;
            self.cardsCaught++;
            // Emit a burst of particles from card position to core
            self._emitCollectionBurst(c.position.x, c.position.y, c.position.z);
          }
        });
      }

      // ──────────────────────────────────────────
      // 6. STORY: COLLECTION PARTICLES → CORE
      // ──────────────────────────────────────────
      if (this.collectedParticles && this.particlePool.length) {
        var posAttr = this.collectedParticles.geometry.attributes.position;
        var colAttr = this.collectedParticles.geometry.attributes.color;
        var anyActive = false;
        var corePos = new THREE.Vector3(0, 0.12, 0.50);
        if (this.coreMesh) this.coreMesh.getWorldPosition(corePos);
        this.particlePool.forEach(function(p) {
          if (!p.active) return;
          anyActive = true;
          p.life -= delta * 1.4;
          if (p.life <= 0) { p.active = false; return; }
          // Move toward target
          var dx = p.tx - p.x, dy = p.ty - p.y, dz = p.tz - p.z;
          var d = Math.sqrt(dx * dx + dy * dy + dz * dz) || 0.001;
          p.x += (dx / d) * p.speed;
          p.y += (dy / d) * p.speed;
          p.z += (dz / d) * p.speed;
        });
        this.collectedParticles.visible = anyActive;
        if (posAttr) {
          var arr = posAttr.array;
          var carr = colAttr.array;
          this.particlePool.forEach(function(p) {
            arr[p.index * 3] = p.x;
            arr[p.index * 3 + 1] = p.y;
            arr[p.index * 3 + 2] = p.z;
            var a = p.life;
            carr[p.index * 3] = 0.13 * a;
            carr[p.index * 3 + 1] = 0.82 * a;
            carr[p.index * 3 + 2] = 0.93 * a;
          });
          posAttr.needsUpdate = true;
          colAttr.needsUpdate = true;
        }
        if (this.coreMesh) {
          this.coreMesh.material.opacity = 0.6 + Math.sin(time * 8) * 0.25 + (anyActive ? 0.3 : 0);
        }
      }

      // ──────────────────────────────────────────
      // 7. ORBITING WEBSITE PANELS & LASER BEAMS
      // ──────────────────────────────────────────
      this.wireframePanels.forEach(function(p, i) {
        p.userData.angle += p.userData.speed * delta;
        p.position.x = Math.cos(p.userData.angle) * p.userData.radius;
        p.position.z = Math.sin(p.userData.angle) * p.userData.radius;
        p.position.y = p.userData.baseY + Math.sin(time * 1.6 + i) * 0.09;
        p.lookAt(0, p.position.y, 0);

        if (self.laserBeams[i]) {
          var beam = self.laserBeams[i];
          var startPos = beam.userData.side === -1 ? handWorldPosLeft : handWorldPosRight;
          var panelWorldPos = new THREE.Vector3();
          p.getWorldPosition(panelWorldPos);
          var posAttr = beam.geometry.attributes.position;
          var arr = posAttr.array;
          arr[0] = startPos.x;
          arr[1] = startPos.y;
          arr[2] = startPos.z;
          arr[3] = panelWorldPos.x;
          arr[4] = panelWorldPos.y;
          arr[5] = panelWorldPos.z;
          posAttr.needsUpdate = true;
          beam.material.opacity = 0.45 + Math.sin(time * 8.0 + i) * 0.35;
        }
      });

      // Laser scanning beam sweeps up and down
      if (this.scannerBeam) {
        this.scannerBeam.position.y = 0.15 + Math.sin(time * 2.6) * 0.90;
        this.scannerBeam.visible = (this.storyAct === 'walking' || this.storyAct === 'reaching');
      }

      // Slow ambient particles & thruster particles
      if (this.ambientParticles) {
        this.ambientParticles.rotation.y += 0.09 * delta;
      }
      if (this.thrusterParticles) {
        this.thrusterParticles.rotation.y += 1.8 * delta;
      }

      // ──────────────────────────────────────────
      // 8. STORY: HOLOGRAPHIC WEBSITE ASSEMBLY
      // ──────────────────────────────────────────
      if (this.websiteRoot && this.websiteComponents.length) {
        var assembling = (this.storyAct === 'assembling' || this.storyAct === 'complete');
        this.websiteRoot.visible = assembling;
        this.websiteComponents.forEach(function(c) {
          if (!c.active && assembling) {
            c.started = true;
            c.active = true;
            c.mesh.visible = true;
            c.mesh.scale.setScalar(0.01);
          }
          if (!c.started) return;
          var t = Math.min(1, ((self.storyTime - (c.delay || 0)) / 0.9));
          if (t <= 0) {
            c.mesh.scale.setScalar(0.01);
            c.mesh.rotation.z = Math.PI * 0.5;
            return;
          }
          var ease = 1 - Math.pow(1 - t, 3);
          var s = ease;
          c.mesh.scale.setScalar(s);
          // Snap rotation toward 0
          c.mesh.rotation.z = (1 - ease) * Math.PI * 0.5;
          c.mesh.position.x = c.targetPos.x * (1 - (1 - ease) * 0.6);
          c.mesh.position.y = c.targetPos.y * (1 - (1 - ease) * 0.6);
          c.mesh.position.z = c.targetPos.z;
          c.mesh.material.opacity = Math.min(1, 0.3 + ease * 0.7);
        });
        // Gentle float of assembled site
        if (assembling) {
          this.websiteRoot.position.y = -0.05 + Math.sin(time * 1.4) * 0.05;
          this.websiteRoot.rotation.y = Math.sin(time * 0.5) * 0.08;
        }
      }

      // Render Scene
      if (this.renderer && this.scene && this.camera) {
        this.renderer.render(this.scene, this.camera);
      }
    },

    /**
     * Synchronizes animation with existing AI generation stages
     */
    setStage: function(stage, message, pct, meta) {
      if (typeof pct === 'number') {
        this.stageProgress = pct;
        var stageIdx = pct < 15 ? 0 : pct < 35 ? 1 : pct < 55 ? 2 : pct < 75 ? 3 : pct < 90 ? 4 : 5;
        this.currentStage = stageIdx;

        // Drive the robot story acts from real progress
        var prevAct = this.storyAct;
        if (pct < 18) { this.storyAct = 'walking'; }
        else if (pct < 40) { this.storyAct = 'reaching'; }
        else if (pct < 62) { this.storyAct = 'catching'; }
        else if (pct < 84) { this.storyAct = 'collecting'; }
        else if (pct < 100) { this.storyAct = 'assembling'; }
        else { this.storyAct = 'complete'; }
        if (prevAct !== this.storyAct) {
          this.storyTime = 0;
          this.reachTarget = null;
          // Set a reach target when reaching begins (hand world position approx)
          if (this.storyAct === 'reaching' && this.rightHand) {
            this.reachTarget = new THREE.Vector3(0.35, 0.55, 1.2);
          }
        }
      }

      // Stage-specific visual metamorphosis
      if (this.scannerBeam) {
        this.scannerBeam.visible = (this.currentStage === 0 || this.currentStage === 1);
      }
      if (this.currentStage === 0 || this.currentStage === 1) {
        // Analyzing requirements: active scanning laser
        if (this.coreLight) this.coreLight.intensity = 3.0;
      } else if (this.currentStage === 2 || this.currentStage === 3) {
        // Planning & Layout: panels expand outward and orbit
        this.wireframePanels.forEach(function(p) { p.userData.radius = p.userData.baseRadius * 1.08; });
      } else if (this.currentStage === 4 || this.currentStage === 5) {
        // Components & Connections: intense core light and active arms
        if (this.coreLight) this.coreLight.intensity = 4.6;
      } else if (this.currentStage >= 6) {
        // Finalizing: panels converge closer to center into finished website model
        this.wireframePanels.forEach(function(p) { p.userData.radius = p.userData.baseRadius * 0.8; });
      }
    },

    /**
     * Handles generation completion or fadeout
     */
    hide: function(success) {
      var self = this;
      if (success) {
        // Celebratory pulse: core flares cyan-gold and halo expands
        if (this.coreLight) this.coreLight.intensity = 5.5;
        if (this.coreMesh) this.coreMesh.material.color.setHex(0x34d399);
        if (this.haloRing) {
          this.haloRing.material.color.setHex(0x10b981);
          this.haloRing.scale.set(1.4, 1.4, 1.4);
        }

        setTimeout(function() {
          self.stop();
        }, 700);
      } else {
        this.stop();
      }
    },

    /**
     * Pauses the animation loop and frees CPU/GPU resources
     */
    stop: function() {
      this.isRunning = false;
      if (this.animId) {
        cancelAnimationFrame(this.animId);
        this.animId = null;
      }
      if (this.container) {
        this.container.style.display = 'none';
      }
    },

    /**
     * Complete resource disposal
     */
    destroy: function() {
      this.stop();
      if (this._onResize) {
        window.removeEventListener('resize', this._onResize);
      }
      if (this.renderer) {
        this.renderer.dispose();
      }
      this.isInitialized = false;
    }
  };

  window.WCRobot3D = WCRobot3D;

  // Auto-init on DOMContentLoaded if canvas exists
  document.addEventListener('DOMContentLoaded', function() {
    WCRobot3D.init();
  });

})(window, document);

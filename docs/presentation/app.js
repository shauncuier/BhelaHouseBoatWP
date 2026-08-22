/**
 * BHELA Presentation Engine Logic
 * Supports automated slide transitions, voiceover narrations,
 * pricing simulation, keyboard navigation, and fullscreen mode.
 */

document.addEventListener('DOMContentLoaded', () => {
  const slides = Array.from(document.querySelectorAll('.slide'));
  const totalSlides = slides.length;
  let currentSlideIndex = 0;
  let isPlaying = false;
  let playTimer = null;
  let slideSecondsElapsed = 0;
  let totalSecondsElapsed = 0;
  let playbackSpeed = 1.0;
  let soundEnabled = true;

  // DOM Elements
  const slideIndicator = document.getElementById('slideIndicator');
  const slideTimer = document.getElementById('slideTimer');
  const progressFill = document.getElementById('progressFill');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const playBtn = document.getElementById('playBtn');
  const speedBtn = document.getElementById('speedBtn');
  const fsBtn = document.getElementById('fsBtn');
  const voiceToggleBtn = document.getElementById('voiceToggleBtn');
  const slideDotsContainer = document.getElementById('slideDots');
  const narratorText = document.getElementById('narratorText');
  const narratorBox = document.getElementById('narratorBox');

  // Narrator Transcripts for Each Slide
  const transcripts = [
    // Slide 1
    "Welcome to the architectural case study of BHELA: The Haor Exclusive. In this presentation, we explore how 3s-Soft engineered an autonomous custom WordPress booking and ERP ecosystem for a luxury houseboat operating on Tanguar Haor, Bangladesh — eliminating operational chaos, stopping inventory leakage, and achieving zero recurring SaaS fees.",
    
    // Slide 2
    "Operating luxury tourism on Tanguar Haor combines high-ticket cabin sales with remote wetland logistics. Generic cloud hospitality software failed because it assumed city hotels and credit cards — completely ignoring cash provisioning at river markets, diesel fuel logs, bKash payments, and cook daily wages, while imposing steep monthly subscriptions.",
    
    // Slide 3
    "Before this system, BHELA faced four critical failure modes: high-friction booking over Facebook DMs with double-booking risks, a complete profitability blindspot where full trips lost money on fuel, three painful days of manual month-end spreadsheet tallying, and zero internal separation of duties.",
    
    // Slide 4
    "3s-Soft engineered a high-performance monorepo with three tightly integrated layers: the bespoke Midnight Monsoon luxury theme with 74% image payload reduction and sub-second TTFB; the custom BHELA Booking Engine with SMS OTP verification; and the enterprise back-office ERP suite with mathematical invariance and an append-only audit trail.",
    
    // Slide 5
    "The guest experience features an interactive multi-step booking wizard with dynamic weekday discounts, weekend rates, holiday surge multipliers, and tiered children pricing. Cryptographically signed invoice tokens prevent timing-attack enumeration, enabling safe 1-tap WhatsApp billing.",
    
    // Slide 6
    "The custom ERP enforces strict 3-tier financial governance: Prepare, Check, and Approve. Once approved by the owner, trip cost sheets are permanently locked. In live testing, the automated statement engine reproduced the owner's manual July 2026 ledger to the exact Taka: 13 trips, 335 guests, and 498,214 Taka gross profit.",
    
    // Slide 7
    "To eliminate inventory shrinkage, the system implements a dual-register model with immutable category codes like BHELA-KIT-0001. Fixed assets are tracked across five condition states, closing counts carry forward automatically into the next month, and variances require mandatory justification before closing.",
    
    // Slide 8
    "Defensive security is enforced across six granular staff roles with an editable permission matrix. Every mutation is recorded to an append-only audit trail with zero DELETE or DROP routes in the codebase, backed by 14 automated headless CLI test suites running on every release.",
    
    // Slide 9
    "The business impact has been transformational: 100% of double bookings eliminated, month-end accounting reduced from 3 days to a single click, over 2,000 dollars saved annually in recurring SaaS tolls, and complete client code sovereignty.",
    
    // Slide 10
    "This project proves that bespoke, zero-bloat software architecture delivers the highest ROI for scaling businesses. Engineered by Jashedul Islam Shaun at 3s-Soft. If you need a custom booking engine, ERP, or high-performance web architecture, connect with 3s-Soft today."
  ];

  // Initialize Slide Dots
  slides.forEach((_, idx) => {
    const dot = document.createElement('div');
    dot.className = `dot ${idx === 0 ? 'active' : ''}`;
    dot.addEventListener('click', () => goToSlide(idx));
    slideDotsContainer.appendChild(dot);
  });

  // Speech Synthesis Helper
  function speakNarration(text) {
    if (!soundEnabled || !('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 1.05 * playbackSpeed;
    utterance.pitch = 1.0;
    
    // Attempt to select a clear English voice if available
    const voices = window.speechSynthesis.getVoices();
    const preferredVoice = voices.find(v => v.lang.startsWith('en') && (v.name.includes('Natural') || v.name.includes('Google') || v.name.includes('Premium')));
    if (preferredVoice) {
      utterance.voice = preferredVoice;
    }
    
    window.speechSynthesis.speak(utterance);
  }

  // Update Slide View
  function updateSlideView() {
    slides.forEach((slide, idx) => {
      slide.classList.remove('active', 'prev');
      if (idx === currentSlideIndex) {
        slide.classList.add('active');
      } else if (idx < currentSlideIndex) {
        slide.classList.add('prev');
      }
    });

    // Update Dots
    const dots = document.querySelectorAll('.dot');
    dots.forEach((dot, idx) => {
      dot.classList.toggle('active', idx === currentSlideIndex);
    });

    // Update Header Metadata
    slideIndicator.textContent = `Slide ${currentSlideIndex + 1} / ${totalSlides}`;
    const progressPercent = ((currentSlideIndex + 1) / totalSlides) * 100;
    progressFill.style.width = `${progressPercent}%`;

    // Update Narrator Text & Speak
    const currentScript = transcripts[currentSlideIndex] || "";
    narratorText.textContent = currentScript;
    if (isPlaying) {
      speakNarration(currentScript);
    }
  }

  function goToSlide(index) {
    if (index >= 0 && index < totalSlides) {
      currentSlideIndex = index;
      slideSecondsElapsed = 0;
      updateSlideView();
    }
  }

  function nextSlide() {
    if (currentSlideIndex < totalSlides - 1) {
      goToSlide(currentSlideIndex + 1);
    } else if (isPlaying) {
      togglePlay(); // Stop when finished
    }
  }

  function prevSlide() {
    if (currentSlideIndex > 0) {
      goToSlide(currentSlideIndex - 1);
    }
  }

  // Timer Tick
  function tick() {
    totalSecondsElapsed++;
    slideSecondsElapsed++;

    const mins = String(Math.floor(totalSecondsElapsed / 60)).padStart(2, '0');
    const secs = String(totalSecondsElapsed % 60).padStart(2, '0');
    slideTimer.textContent = `${mins}:${secs}`;

    // Check slide auto-advance
    const currentSlideElem = slides[currentSlideIndex];
    const duration = parseInt(currentSlideElem.dataset.duration || '20', 10);
    
    if (slideSecondsElapsed >= duration / playbackSpeed) {
      nextSlide();
    }
  }

  function togglePlay() {
    isPlaying = !isPlaying;
    if (isPlaying) {
      playBtn.textContent = '⏸ Pause';
      playBtn.style.background = '#E86529';
      playTimer = setInterval(tick, 1000);
      speakNarration(transcripts[currentSlideIndex]);
    } else {
      playBtn.textContent = '▶ Play';
      playBtn.style.background = 'var(--cta)';
      clearInterval(playTimer);
      if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
      }
    }
  }

  // Event Listeners
  prevBtn.addEventListener('click', prevSlide);
  nextBtn.addEventListener('click', nextSlide);
  playBtn.addEventListener('click', togglePlay);

  speedBtn.addEventListener('click', () => {
    if (playbackSpeed === 1.0) {
      playbackSpeed = 1.5;
      speedBtn.textContent = '1.5x';
    } else if (playbackSpeed === 1.5) {
      playbackSpeed = 2.0;
      speedBtn.textContent = '2.0x';
    } else {
      playbackSpeed = 1.0;
      speedBtn.textContent = '1.0x';
    }
  });

  voiceToggleBtn.addEventListener('click', () => {
    soundEnabled = !soundEnabled;
    voiceToggleBtn.textContent = soundEnabled ? '🔊 Sound On' : '🔇 Sound Off';
    if (!soundEnabled && 'speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    } else if (soundEnabled && isPlaying) {
      speakNarration(transcripts[currentSlideIndex]);
    }
  });

  fsBtn.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(err => console.log(err));
      fsBtn.textContent = '🗗 Exit Fullscreen';
    } else {
      document.exitFullscreen();
      fsBtn.textContent = '⛶ Fullscreen';
    }
  });

  // Keyboard Navigation
  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === 'PageDown') {
      nextSlide();
    } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
      prevSlide();
    } else if (e.key === ' ' || e.code === 'Space') {
      e.preventDefault();
      togglePlay();
    } else if (e.key === 'f' || e.key === 'F') {
      fsBtn.click();
    }
  });

  // Interactive Pricing Simulator Widget (Slide 5)
  const simCabin = document.getElementById('simCabin');
  const simPills = document.querySelectorAll('.sim-pill');
  const simResultPrice = document.getElementById('simResultPrice');
  let selectedDayType = 'weekday';

  function calculateSimulation() {
    if (!simCabin || !simResultPrice) return;
    const baseCabinRate = parseInt(simCabin.value, 10);
    const childFee = 5000; // Flat fee for age 6
    
    let multiplier = 1.0;
    if (selectedDayType === 'weekday') {
      multiplier = 0.8; // 20% discount on cabin
    } else if (selectedDayType === 'holiday') {
      multiplier = 1.15; // 15% holiday surge
    }

    let finalPrice;
    if (baseCabinRate >= 50000) {
      // Full Boat Charter
      finalPrice = Math.round(baseCabinRate * multiplier);
    } else {
      // Cabin Rate (discount applies to cabin, child fee flat)
      finalPrice = Math.round(baseCabinRate * multiplier) + childFee;
    }

    simResultPrice.textContent = `৳${finalPrice.toLocaleString('en-IN')}`;
  }

  if (simCabin) {
    simCabin.addEventListener('change', calculateSimulation);
    simPills.forEach(pill => {
      pill.addEventListener('click', () => {
        simPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        selectedDayType = pill.dataset.type;
        calculateSimulation();
      });
    });
    calculateSimulation();
  }

  // Pre-load voices
  if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = () => {
      window.speechSynthesis.getVoices();
    };
  }

  // Initial View
  updateSlideView();
});

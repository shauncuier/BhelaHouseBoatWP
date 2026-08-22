# -*- coding: utf-8 -*-
"""
Generate the Ultra-Premium BHELA LinkedIn Case Study Presentation PDF.
Designed to look like a world-class agency / Apple / McKinsey keynote deck:
- 16:9 Widescreen aspect ratio (297mm x 167mm or 1920x1080)
- High-contrast Midnight Obsidian & Emerald palette with Glowing Gold & Coral accents
- Large, bold typography (Plus Jakarta Sans, JetBrains Mono) with spacious padding
- Floating glassmorphic cards with glowing borders and soft drop shadows
- Prominent high-resolution photography with rounded frames and luxury badges
- Base64 inlined images for 100% reliable rendering

Run: python docs/generate-linkedin-case-study-pdf.py
"""

import os
import sys
import base64
import subprocess

HERE = os.path.dirname(os.path.abspath(__file__))
WP_CONTENT = os.path.normpath(os.path.join(HERE, ".."))
THEME_IMG_DIR = os.path.join(WP_CONTENT, "themes", "bhela", "assets", "images")

DECK_HTML = os.path.join(HERE, "case-study-deck.html")
OUT_PDF = os.path.join(HERE, "BHELA-Case-Study-LinkedIn.pdf")
OUT_MD = os.path.join(HERE, "BHELA-Case-Study-LinkedIn.md")

CANDIDATES = [
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
]


def find_browser():
    for path in CANDIDATES:
        if os.path.exists(path):
            return path
    return None


def get_image_base64(rel_path):
    full_path = os.path.join(THEME_IMG_DIR, rel_path)
    if not os.path.exists(full_path):
        print(f"[WARN] Image not found: {full_path}")
        return ""
    mime = "image/png" if full_path.endswith(".png") else "image/jpeg"
    with open(full_path, "rb") as f:
        encoded = base64.b64encode(f.read()).decode("utf-8")
    return f"data:{mime};base64,{encoded}"


def build_deck_html():
    img_logo = get_image_base64("logo.png")
    img_boat_hero = get_image_base64("spots/spot-2.jpg") if os.path.exists(os.path.join(THEME_IMG_DIR, "spots", "spot-2.jpg")) else get_image_base64("boat/exterior-1.jpg")
    img_haor_scenic = get_image_base64("hero/hero-haor.jpg")
    img_boat_rooftop = get_image_base64("boat/rooftop-1.jpg")
    img_cabin_luxury = get_image_base64("cabins/cabin-3.jpg")
    img_dining_lawn = get_image_base64("spots/spot-1.jpg")

    html = f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BHELA — Case Study Presentation Deck | 3s-Soft</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    @page {{
      size: 297mm 175mm;
      margin: 0;
    }}

    :root {{
      --bg-dark: #051315;
      --bg-card: #0A2226;
      --bg-card-elevated: #0F2F34;
      --bg-card-hover: #153E45;
      --primary: #00D2C4;
      --primary-dim: rgba(0, 210, 196, 0.15);
      --accent-gold: #FFC857;
      --accent-gold-dim: rgba(255, 200, 87, 0.15);
      --accent-coral: #FF5A36;
      --accent-coral-dim: rgba(255, 90, 54, 0.15);
      --text-white: #FFFFFF;
      --text-light: #E2EFF1;
      --text-dim: #94B3B8;
      --border-card: rgba(255, 255, 255, 0.1);
      --border-highlight: rgba(0, 210, 196, 0.4);
      --border-gold: rgba(255, 200, 87, 0.4);
      --danger-bg: rgba(255, 71, 87, 0.1);
      --danger-border: rgba(255, 71, 87, 0.35);
      --danger-text: #FF7B88;
      --success-bg: rgba(46, 213, 115, 0.12);
      --success-text: #4EEDA4;
      
      --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }}

    * {{
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }}

    body {{
      background-color: var(--bg-dark);
      color: var(--text-white);
      font-family: var(--font-sans);
      margin: 0;
      padding: 0;
    }}

    .slide {{
      width: 297mm;
      height: 175mm;
      max-width: 297mm;
      max-height: 175mm;
      position: relative;
      overflow: hidden;
      page-break-after: always;
      background: var(--bg-dark);
      background-image: 
        radial-gradient(circle at 5% 10%, rgba(0, 210, 196, 0.18) 0%, transparent 45%),
        radial-gradient(circle at 95% 90%, rgba(255, 90, 54, 0.12) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(10, 34, 38, 0.6) 0%, transparent 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 12mm 16mm 10mm 16mm;
    }}

    /* Header */
    .slide-hdr {{
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 3.5mm;
      border-bottom: 1px solid var(--border-card);
    }}

    .hdr-brand {{
      display: flex;
      align-items: center;
      gap: 12px;
    }}

    .hdr-logo {{
      height: 28px;
      width: auto;
      object-fit: contain;
    }}

    .hdr-title {{
      font-size: 13px;
      font-weight: 800;
      letter-spacing: 1.5px;
      color: var(--accent-gold);
    }}

    .hdr-sub {{
      font-size: 12px;
      color: var(--primary);
      font-weight: 500;
    }}

    .hdr-badge {{
      font-family: var(--font-mono);
      font-size: 10px;
      font-weight: 700;
      color: var(--accent-gold);
      background: var(--accent-gold-dim);
      border: 1px solid var(--border-gold);
      padding: 4px 10px;
      border-radius: 20px;
      letter-spacing: 1px;
    }}

    /* Footer */
    .slide-ftr {{
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 3mm;
      border-top: 1px solid var(--border-card);
      font-size: 10.5px;
      color: var(--text-dim);
      font-family: var(--font-mono);
    }}

    .ftr-left {{
      display: flex;
      align-items: center;
      gap: 10px;
    }}

    .ftr-dot {{
      color: var(--accent-coral);
    }}

    .ftr-page {{
      color: var(--primary);
      font-weight: 700;
      background: var(--primary-dim);
      padding: 3px 8px;
      border-radius: 4px;
      border: 1px solid var(--border-highlight);
    }}

    /* Content Typography */
    .slide-content {{
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 2mm 0;
    }}

    .eyebrow {{
      font-family: var(--font-mono);
      font-size: 11px;
      font-weight: 700;
      color: var(--accent-gold);
      letter-spacing: 2px;
      margin-bottom: 2mm;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 8px;
    }}

    .eyebrow::before {{
      content: '';
      display: inline-block;
      width: 12px;
      height: 3px;
      background: var(--accent-coral);
      border-radius: 2px;
    }}

    .main-title {{
      font-size: 26px;
      font-weight: 800;
      line-height: 1.2;
      color: var(--text-white);
      letter-spacing: -0.5px;
      margin-bottom: 2mm;
    }}

    .main-desc {{
      font-size: 13.5px;
      color: var(--text-dim);
      line-height: 1.45;
      margin-bottom: 4mm;
      max-width: 95%;
    }}

    .text-gradient {{
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent-gold) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }}

    /* High Impact Cards */
    .card {{
      background: var(--bg-card);
      border: 1px solid var(--border-card);
      border-radius: 12px;
      padding: 16px 18px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      position: relative;
    }}

    .card.glow-teal {{
      border-color: var(--border-highlight);
      background: linear-gradient(135deg, #0A2428 0%, #0F3238 100%);
    }}

    .card.glow-gold {{
      border-color: var(--border-gold);
      background: linear-gradient(135deg, #1A281E 0%, #132724 100%);
    }}

    .card.glow-danger {{
      border-color: var(--danger-border);
      background: var(--danger-bg);
    }}

    .card-head {{
      font-size: 14.5px;
      font-weight: 700;
      color: var(--text-white);
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }}

    .card-body-text {{
      font-size: 12px;
      color: var(--text-light);
      line-height: 1.45;
    }}

    /* Photo Containers */
    .photo-card {{
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border-highlight);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
      position: relative;
      background: var(--bg-card-elevated);
    }}

    .photo-card img {{
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }}

    .photo-badge {{
      position: absolute;
      bottom: 8px;
      left: 10px;
      right: 10px;
      background: rgba(5, 19, 21, 0.88);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 200, 87, 0.35);
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 10.5px;
      font-weight: 600;
      color: var(--accent-gold);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }}

    /* Grids */
    .grid-2 {{
      display: grid;
      grid-template-columns: 1.15fr 1fr;
      gap: 18px;
      align-items: center;
    }}

    .grid-3 {{
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }}

    .grid-4 {{
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }}

    /* Pills & Badges */
    .tag {{
      display: inline-block;
      font-family: var(--font-mono);
      font-size: 9.5px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 4px;
      text-transform: uppercase;
    }}

    .tag-teal {{
      background: var(--primary-dim);
      color: var(--primary);
      border: 1px solid var(--border-highlight);
    }}

    .tag-gold {{
      background: var(--accent-gold-dim);
      color: var(--accent-gold);
      border: 1px solid var(--border-gold);
    }}

    .tag-coral {{
      background: var(--accent-coral-dim);
      color: var(--accent-coral);
      border: 1px solid rgba(255, 90, 54, 0.4);
    }}

    .tag-danger {{
      background: rgba(255, 71, 87, 0.2);
      color: var(--danger-text);
      border: 1px solid var(--danger-border);
    }}

    .tag-success {{
      background: var(--success-bg);
      color: var(--success-text);
      border: 1px solid rgba(78, 237, 164, 0.35);
    }}

    /* Stats Grid */
    .stat-row {{
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 12px;
    }}

    .stat-card {{
      background: var(--bg-card);
      border: 1px solid var(--border-highlight);
      border-radius: 10px;
      padding: 14px 12px;
      text-align: center;
    }}

    .stat-big {{
      font-size: 26px;
      font-weight: 800;
      font-family: var(--font-mono);
      color: var(--accent-coral);
      margin-bottom: 3px;
      letter-spacing: -0.5px;
    }}

    .stat-title {{
      font-size: 12px;
      font-weight: 700;
      color: var(--text-white);
      margin-bottom: 2px;
    }}

    .stat-desc {{
      font-size: 10px;
      color: var(--text-dim);
    }}

    /* Table */
    .custom-table {{
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-card);
      font-size: 11.5px;
    }}

    .custom-table th {{
      background: var(--bg-card-elevated);
      color: var(--primary);
      text-align: left;
      padding: 8px 12px;
      font-weight: 700;
      border-bottom: 1px solid var(--border-highlight);
    }}

    .custom-table td {{
      padding: 7.5px 12px;
      border-bottom: 1px solid var(--border-card);
      color: var(--text-light);
      background: var(--bg-card);
    }}

    .custom-table tr:last-child td {{
      border-bottom: none;
    }}

    /* Bullet List */
    .feature-list {{
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
      font-size: 12.5px;
      color: var(--text-light);
    }}

    .feature-list li {{
      display: flex;
      align-items: flex-start;
      gap: 8px;
      line-height: 1.4;
    }}

    .feature-list li .icon {{
      color: var(--primary);
      font-weight: 800;
    }}
  </style>
</head>
<body>

  <!-- =========================================================================
       SLIDE 1: COVER SLIDE
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <div>
          <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
          <span class="hdr-sub"> &bull; Tanguar Haor, Sunamganj</span>
        </div>
      </div>
      <div class="hdr-badge">ENTERPRISE CASE STUDY</div>
    </header>

    <div class="slide-content">
      <div class="grid-2" style="grid-template-columns: 1.4fr 1fr; gap: 24px;">
        <div>
          <div class="eyebrow">BESPOKE HOSPITALITY SOFTWARE ENGINEERING</div>
          <h1 class="main-title" style="font-size: 32px; margin-bottom: 3.5mm;">
            Building an <span class="text-gradient">Autonomous Operating System</span> for Luxury Houseboat Tourism
          </h1>
          <p class="main-desc" style="font-size: 14.5px; line-height: 1.5; margin-bottom: 4mm;">
            How a bespoke WordPress monorepo replaced phone/DM chaos, paper ledgers, and inventory shrinkage with 3-tier financial governance, dynamic pricing, and $0 recurring SaaS fees.
          </p>

          <div style="display: flex; gap: 10px;">
            <span class="tag tag-gold">100% Client Code Ownership</span>
            <span class="tag tag-teal">Zero Page-Builder Bloat</span>
            <span class="tag tag-coral">৳0 Reconciled Calculation Drift</span>
          </div>
        </div>

        <div class="photo-card" style="height: 195px;">
          <img src="{img_boat_hero}" alt="BHELA Houseboat">
          <div class="photo-badge">
            <span>BHELA Luxury Houseboat</span>
            <span style="color: var(--primary);">Tanguar Haor</span>
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 14px;">
        <div class="card glow-teal" style="padding: 10px 12px;">
          <div style="font-size: 9.5px; font-family: var(--font-mono); color: var(--primary); font-weight: 700;">CLIENT &amp; VESSEL</div>
          <div style="font-size: 13px; font-weight: 700; color: #FFF;">BHELA Houseboat</div>
          <div style="font-size: 10.5px; color: var(--text-dim);">KeyToBD &bull; Luxury Cruise</div>
        </div>
        <div class="card glow-teal" style="padding: 10px 12px;">
          <div style="font-size: 9.5px; font-family: var(--font-mono); color: var(--primary); font-weight: 700;">ARCHITECT &amp; DEV</div>
          <div style="font-size: 13px; font-weight: 700; color: #FFF;">3s-Soft (Shaun)</div>
          <div style="font-size: 10.5px; color: var(--text-dim);">3s-soft.com</div>
        </div>
        <div class="card glow-teal" style="padding: 10px 12px;">
          <div style="font-size: 9.5px; font-family: var(--font-mono); color: var(--primary); font-weight: 700;">PLATFORM STACK</div>
          <div style="font-size: 13px; font-weight: 700; color: #FFF;">Custom Monorepo</div>
          <div style="font-size: 10.5px; color: var(--text-dim);">PHP 8+ &bull; Vanilla JS &bull; ERP</div>
        </div>
        <div class="card glow-gold" style="padding: 10px 12px;">
          <div style="font-size: 9.5px; font-family: var(--font-mono); color: var(--accent-gold); font-weight: 700;">KEY DELIVERED ROI</div>
          <div style="font-size: 13px; font-weight: 700; color: var(--accent-gold);">1-Click Month-End</div>
          <div style="font-size: 10.5px; color: var(--text-dim);">100% Double-Bookings Fixed</div>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft (Shaun)</span>
        <span class="ftr-dot">&bull;</span>
        <span>Full Source Code Ownership</span>
      </div>
      <div class="ftr-page">Slide 01 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 2: BUSINESS CONTEXT
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Executive Context</span>
      </div>
      <div class="hdr-badge">01 &bull; BUSINESS CONTEXT</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">THE OPERATIONAL REALITY</div>
      <h2 class="main-title">Scaling Luxury Hospitality on Bangladesh's Most Pristine Wetland</h2>
      <p class="main-desc">High average order value (AOV) experiential tourism combined with off-grid river logistics.</p>

      <div class="grid-2">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">THE OPERATION</span>
            <span>Experiential Tourism Dynamics</span>
          </div>
          <ul class="feature-list" style="margin-top: 10px;">
            <li><span class="icon">&#10003;</span> <strong>6 Luxury Cabins &amp; Charters:</strong> 5 layout classes accommodating 30–35 guests or private corporate/family charters.</li>
            <li><span class="icon">&#10003;</span> <strong>Peak Monsoon Surge Window:</strong> Short, high-intensity season requiring surge multipliers, weekend pricing, and multi-tier child brackets.</li>
            <li><span class="icon">&#10003;</span> <strong>Remote River Logistics:</strong> Provisioning at local river markets, jetty handling, fuel docks, and bKash/Nagad transactions.</li>
          </ul>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; height: 110px;">
            <div class="photo-card">
              <img src="{img_haor_scenic}" alt="Tanguar Haor">
              <div class="photo-badge" style="font-size: 9px; padding: 4px 6px;">Tanguar Haor Wetland</div>
            </div>
            <div class="photo-card">
              <img src="{img_boat_rooftop}" alt="Rooftop Deck">
              <div class="photo-badge" style="font-size: 9px; padding: 4px 6px;">Open-Sky Rooftop Deck</div>
            </div>
          </div>

          <div class="card glow-danger" style="padding: 12px 14px;">
            <div class="card-head" style="color: var(--danger-text); font-size: 13px; margin-bottom: 2px;">
              <span>Why Generic Hospitality SaaS Failed</span>
            </div>
            <p class="card-body-text" style="font-size: 11px;">
              Off-the-shelf platforms (Cloudbeds, Guesty) charge $150–$300/mo + 2–3% booking cuts, but fail completely on cash advances, diesel consumption logs, and remote river market receipts.
            </p>
          </div>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 02 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 3: THE OPERATIONAL CRISIS (BEFORE)
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; The Problem</span>
      </div>
      <div class="hdr-badge">02 &bull; THE OPERATIONAL CRISIS</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">PRE-SYSTEM VULNERABILITY VECTORS</div>
      <h2 class="main-title">The Four Failure Modes of the Pre-System Era</h2>
      <p class="main-desc">Fragmented manual workflows leaked margins, generated customer pricing disputes, and blinded leadership.</p>

      <div class="grid-4">
        <div class="card glow-danger">
          <div class="card-head" style="color: var(--danger-text);">
            <span class="tag tag-danger">01</span>
            <span>Booking &amp; Pricing Chaos</span>
          </div>
          <p class="card-body-text">
            Bookings taken across WhatsApp DMs and phone calls. Quotes quoted from memory caused frequent errors in holiday rates, child brackets, and double bookings.
          </p>
        </div>

        <div class="card glow-danger">
          <div class="card-head" style="color: var(--danger-text);">
            <span class="tag tag-danger">02</span>
            <span>The Profitability Blindspot</span>
          </div>
          <p class="card-body-text">
            Cruises sailed fully booked yet lost money on diesel surges or market provisioning. Tracked on paper receipts, the owner could not identify unprofitable trips.
          </p>
        </div>

        <div class="card glow-danger">
          <div class="card-head" style="color: var(--danger-text);">
            <span class="tag tag-danger">03</span>
            <span>3-Day Month-End Agony</span>
          </div>
          <p class="card-body-text">
            Reconciling monthly revenue against costs took 3 full days of manual entry from messy notebooks and WhatsApp receipts. Figures constantly drifted.
          </p>
        </div>

        <div class="card glow-danger">
          <div class="card-head" style="color: var(--danger-text);">
            <span class="tag tag-danger">04</span>
            <span>Zero Internal Controls</span>
          </div>
          <p class="card-body-text">
            Staff spending cash approved their own expenses. Kitchen supplies, expensive blankets, and engine spares vanished with zero audit trail.
          </p>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 03 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 4: THE MONOREPO ARCHITECTURE
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; System Architecture</span>
      </div>
      <div class="hdr-badge">03 &bull; ARCHITECTURE</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">ENGINEERING BLUEPRINT</div>
      <h2 class="main-title">The Solution: Zero-Bloat High-Performance Monorepo</h2>
      <p class="main-desc">A unified three-layer custom ecosystem designed for speed, security, and full client ownership.</p>

      <div class="grid-3" style="margin-bottom: 12px;">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-teal">LAYER 1</span>
            <span>Midnight Monsoon Theme</span>
          </div>
          <p style="font-family: var(--font-mono); font-size: 10.5px; color: var(--primary); margin-bottom: 6px;">`themes/bhela/` &bull; Guest UX</p>
          <ul class="feature-list" style="font-size: 11px; gap: 4px;">
            <li><span class="icon">&#10003;</span> Semantic PHP &bull; Vanilla JS (Zero jQuery)</li>
            <li><span class="icon">&#10003;</span> 74% image compression via WebP pipeline</li>
            <li><span class="icon">&#10003;</span> Live rate injection from database</li>
            <li><span class="icon">&#10003;</span> Bangla-first luxury guest design</li>
          </ul>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-teal">LAYER 2</span>
            <span>BHELA Booking Engine</span>
          </div>
          <p style="font-family: var(--font-mono); font-size: 10.5px; color: var(--primary); margin-bottom: 6px;">`plugins/bhela-booking/` &bull; Core</p>
          <ul class="feature-list" style="font-size: 11px; gap: 4px;">
            <li><span class="icon">&#10003;</span> Real-time cabin &amp; charter lock</li>
            <li><span class="icon">&#10003;</span> SMS OTP verification gate (anti-spam)</li>
            <li><span class="icon">&#10003;</span> Dynamic multi-bracket pricing rules</li>
            <li><span class="icon">&#10003;</span> Cryptographic signed invoice tokens</li>
          </ul>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-teal">LAYER 3</span>
            <span>Custom ERP &amp; Governance</span>
          </div>
          <p style="font-family: var(--font-mono); font-size: 10.5px; color: var(--primary); margin-bottom: 6px;">`includes/costs.php, statement.php`</p>
          <ul class="feature-list" style="font-size: 11px; gap: 4px;">
            <li><span class="icon">&#10003;</span> 3-Tier Prepare &rarr; Check &rarr; Approve</li>
            <li><span class="icon">&#10003;</span> Locked cost sheets &amp; 1-click statements</li>
            <li><span class="icon">&#10003;</span> Dual-register inventory carry-forward</li>
            <li><span class="icon">&#10003;</span> Append-only log (Zero DELETE routes)</li>
          </ul>
        </div>
      </div>

      <div class="card" style="padding: 8px 14px; background: rgba(5, 19, 21, 0.8); font-size: 11.5px; display: flex; justify-content: space-between; align-items: center;">
        <span style="color: var(--accent-gold); font-weight: 700;">Standards: PHP 8.0+ Strict &bull; WP Custom Post Types &bull; WCAG AA Tested</span>
        <span class="tag tag-success">14/14 Automated CLI Tests Passing</span>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 04 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 5: GUEST EXPERIENCE & CABIN PHOTO
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Dynamic Pricing</span>
      </div>
      <div class="hdr-badge">04 &bull; GUEST EXPERIENCE</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">RESERVATION ENGINE</div>
      <h2 class="main-title">Dynamic Pricing Matrix &amp; Instant WhatsApp Invoicing</h2>
      <p class="main-desc">Automating complex hospitality rates while eliminating booking leakage.</p>

      <div class="grid-2" style="align-items: start;">
        <table class="custom-table">
          <thead>
            <tr>
              <th style="width: 32%;">Capability</th>
              <th>Engine Implementation</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>6-Cabin Matrix</strong></td>
              <td>5 unique cabin types + Full-Boat Charter mode.</td>
            </tr>
            <tr>
              <td><strong>Age Brackets</strong></td>
              <td>Adults (9+): 100% &bull; Children (4-8): ৳5k flat &bull; Infants: Free.</td>
            </tr>
            <tr>
              <td><strong>Surge &amp; Holiday</strong></td>
              <td>Auto weekend multipliers &amp; up to 20% weekday discount.</td>
            </tr>
            <tr>
              <td><strong>SMS OTP Gateway</strong></td>
              <td>BulkSMSBD API eliminates fake/spam reservations.</td>
            </tr>
            <tr>
              <td><strong>Signed Invoices</strong></td>
              <td>32-byte salted token prevents timing-attack enumeration.</td>
            </tr>
          </tbody>
        </table>

        <div class="photo-card" style="height: 185px;">
          <img src="{img_cabin_luxury}" alt="Luxury Cabin Interior">
          <div class="photo-badge">
            <span>Haor Bilash &bull; Luxury AC Balcony Cabin</span>
            <span style="color: var(--primary);">Real Photography</span>
          </div>
        </div>
      </div>

      <div class="card glow-teal" style="padding: 8px 12px; margin-top: 10px; font-size: 11px;">
        <strong style="color: var(--primary);">Security Fix (SEC-001):</strong> Replaced predictable timestamp invoice URLs with cryptographically salted tokens (`_bhela_invoice_secret`), securing guest PII for instant WhatsApp sharing.
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 05 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 6: 3-TIER ERP GOVERNANCE
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Financial ERP</span>
      </div>
      <div class="hdr-badge">05 &bull; FINANCIAL GOVERNANCE</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">FINANCIAL INTEGRITY</div>
      <h2 class="main-title">3-Tier Governance &amp; Mathematical Invariance</h2>
      <p class="main-desc">Strict separation of duties transforms paper receipts into tamper-proof profit statements.</p>

      <div class="grid-3" style="margin-bottom: 12px;">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">STEP 1</span>
            <span>PREPARE</span>
          </div>
          <p style="color: var(--primary); font-size: 11px; margin-bottom: 4px;">Crew / Storekeeper</p>
          <p class="card-body-text">
            Records actual trip costs across 14 owner-configurable heads: Diesel, Groceries, Fresh Fish, Gas, Jetty, Crew Wages. 3 split payment columns.
          </p>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">STEP 2</span>
            <span>CHECK</span>
          </div>
          <p style="color: var(--primary); font-size: 11px; margin-bottom: 4px;">Operations Manager</p>
          <p class="card-body-text">
            Audits vendor bills, checks grocery issue slips, and verifies passenger counts. The checker cannot be the user who prepared the cost sheet.
          </p>
        </div>

        <div class="card glow-gold">
          <div class="card-head" style="color: var(--accent-gold);">
            <span class="tag tag-coral">STEP 3</span>
            <span>APPROVE &amp; LOCK</span>
          </div>
          <p style="color: var(--accent-gold); font-size: 11px; margin-bottom: 4px;">Owner / Managing Director</p>
          <p class="card-body-text">
            Final approval permanently locks the sheet into the general ledger. Approved figures feed the Monthly Statement. Requires audited admin override to edit.
          </p>
        </div>
      </div>

      <div class="card glow-gold" style="padding: 12px 14px;">
        <div style="font-size: 12.5px; color: #FFF; line-height: 1.45;">
          <strong style="color: var(--accent-gold); font-size: 13.5px;">📊 July 2026 Live Validation:</strong> The automated statement engine reproduced the owner's manual ledger to the exact Taka: <strong>13 trips &bull; 335 guests &bull; ৳498,214 gross profit</strong> with zero calculation drift.
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 06 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 7: INVENTORY & DINING PHOTO
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Inventory Integrity</span>
      </div>
      <div class="hdr-badge">06 &bull; INVENTORY INTEGRITY</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">ANTI-SHRINKAGE SYSTEM</div>
      <h2 class="main-title">Dual-Register Stock Engine: Eliminating Leakage</h2>
      <p class="main-desc">Permanent category tagging, 5-state condition logs, and automated monthly carry-forward.</p>

      <div class="grid-3" style="margin-bottom: 12px;">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">REGISTER</span>
            <span>Permanent Asset Tags</span>
          </div>
          <p class="card-body-text">
            Category IDs (e.g. `BHELA-KIT-0001`, `BHELA-ENG-0012`). Logs model, serial, purchase bill, warranty, and assigned custodian.
          </p>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">LIFECYCLE</span>
            <span>5-State Condition Log</span>
          </div>
          <p class="card-body-text">
            Tracked across: <strong>Good &rarr; Repairable &rarr; Under Repair &rarr; Damaged &rarr; Scrapped</strong>. Separates consumables from capital assets.
          </p>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">AUDIT</span>
            <span>Variance Reconciliation</span>
          </div>
          <p class="card-body-text">
            Physical count vs. system count reconciled at month-end. Closing count auto carry-forwards as new month opening. Variances require justification.
          </p>
        </div>
      </div>

      <div class="grid-2" style="align-items: center;">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Dimension</th>
              <th>Legacy Notebook</th>
              <th>BHELA Custom ERP Engine</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Damaged Gear</strong></td>
              <td>Discarded quietly; re-bought</td>
              <td><span class="tag tag-success">4 condition states &amp; repair history</span></td>
            </tr>
            <tr>
              <td><strong>Month Carry</strong></td>
              <td>Manual re-typing</td>
              <td><span class="tag tag-success">100% Automated locked carry-forward</span></td>
            </tr>
            <tr>
              <td><strong>Stock Import</strong></td>
              <td>Days of re-entry</td>
              <td><span class="tag tag-success">4-step smart CSV dry-run importer</span></td>
            </tr>
          </tbody>
        </table>

        <div class="photo-card" style="height: 110px;">
          <img src="{img_dining_lawn}" alt="Rooftop Dining Deck">
          <div class="photo-badge">
            <span>Rooftop Dining Deck &amp; Open Lawn</span>
            <span style="color: var(--primary);">Real Photography</span>
          </div>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 07 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 8: SECURITY
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Enterprise Security</span>
      </div>
      <div class="hdr-badge">07 &bull; DEFENSIVE SECURITY</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">ENTERPRISE ACCESS CONTROL</div>
      <h2 class="main-title">Defensive Security &amp; Append-Only Audit Trail</h2>
      <p class="main-desc">Zero-trust role segregation, permanent system memory, and automated regression testing.</p>

      <div class="grid-2" style="margin-bottom: 12px;">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">ROLES</span>
            <span>6 Granular Staff Roles</span>
          </div>
          <p class="card-body-text" style="margin-bottom: 8px;">
            Configurable matrix: <em>Administrator, Manager, Booking Staff, Cost Preparer, Cost Checker, Storekeeper</em>.
          </p>
          <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            <span class="tag tag-teal">Store: Stock Only</span>
            <span class="tag tag-teal">Staff: Bookings Only</span>
            <span class="tag tag-teal">Accounts: Cost Sheets Only</span>
          </div>
        </div>

        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">AUDIT</span>
            <span>Append-Only Mutation Log</span>
          </div>
          <p class="card-body-text" style="margin-bottom: 6px;">
            Every change (actor, timestamp, field, prior value &rarr; new value) is written to a permanent audit register.
          </p>
          <div style="background: #020C0E; border: 1px solid var(--border-card); border-radius: 4px; padding: 6px 8px; font-family: var(--font-mono); font-size: 10.5px; color: var(--text-light);">
            <div><span style="color: var(--primary);">2026-08-18</span> &bull; cost_sheet_142 &bull; diesel: ৳14.5k &rarr; ৳15.2k</div>
            <div style="color: var(--accent-gold); font-weight: 700; margin-top: 2px;">&#9888; ZERO DELETE OR DROP ROUTES IN CODEBASE</div>
          </div>
        </div>
      </div>

      <div class="card glow-teal" style="padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12.5px; font-weight: 700; color: var(--accent-gold);">14 Headless Automated CLI Test Suites (`tests/run.php`)</span>
        <div style="display: flex; gap: 6px;">
          <span class="tag tag-success">security: PASS</span>
          <span class="tag tag-success">july-statement: PASS</span>
          <span class="tag tag-success">inventory: PASS</span>
          <span class="tag tag-success">otp-auth: PASS</span>
          <span class="tag tag-success">wcag-aa: PASS</span>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 08 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 9: RESULTS & ROI
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Measured Impact</span>
      </div>
      <div class="hdr-badge">08 &bull; MEASURABLE RESULTS</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">DELIVERED BUSINESS ROI</div>
      <h2 class="main-title">Measurable Business Results &amp; Operational Velocity</h2>
      <p class="main-desc">Delivering undeniable time savings, profit transparency, and data sovereignty.</p>

      <div class="stat-row">
        <div class="stat-card">
          <div class="stat-big">100%</div>
          <div class="stat-title">Double Bookings Fixed</div>
          <div class="stat-desc">Real-time availability lock across all channels</div>
        </div>

        <div class="stat-card">
          <div class="stat-big" style="font-size: 20px;">3 Days &rarr; 1 Click</div>
          <div class="stat-title">Month-End Audit</div>
          <div class="stat-desc">Instant verified gross profit calculation</div>
        </div>

        <div class="stat-card">
          <div class="stat-big">$0 / mo</div>
          <div class="stat-title">Recurring SaaS Fees</div>
          <div class="stat-desc">Saved $2,000+/yr in SaaS subscriptions &amp; cuts</div>
        </div>

        <div class="stat-card">
          <div class="stat-big">100%</div>
          <div class="stat-title">Source Ownership</div>
          <div class="stat-desc">Zero vendor lock-in; complete sovereignty</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card glow-teal" style="padding: 12px 16px;">
          <strong style="color: var(--primary); font-size: 13px;">⚡ Operational Velocity:</strong> Front desk reservation turnaround accelerated 4x with instant WhatsApp quote generation and pre-filled signed invoice links.
        </div>
        <div class="card glow-teal" style="padding: 12px 16px;">
          <strong style="color: var(--primary); font-size: 13px;">🛡️ Audit Defense:</strong> Partner dividend and tax disputes eliminated through cryptographically locked historical statements and signed trip cost sheets.
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 09 / 10</div>
    </footer>
  </section>

  <!-- =========================================================================
       SLIDE 10: TAKEAWAYS & CREDITS
       ========================================================================= -->
  <section class="slide">
    <header class="slide-hdr">
      <div class="hdr-brand">
        <img src="{img_logo}" alt="BHELA" class="hdr-logo">
        <span class="hdr-title">BHELA: THE HAOR EXCLUSIVE</span>
        <span class="hdr-sub"> &bull; Summary &amp; Contact</span>
      </div>
      <div class="hdr-badge">09 &bull; STRATEGIC TAKEAWAYS</div>
    </header>

    <div class="slide-content">
      <div class="eyebrow">KEY LESSONS &amp; CREDITS</div>
      <h2 class="main-title">Key Architectural Lessons &amp; Project Credits</h2>
      <p class="main-desc">Why bespoke software architecture remains the highest-ROI investment for scaling businesses.</p>

      <div class="grid-2">
        <div class="card glow-teal">
          <div class="card-head">
            <span class="tag tag-gold">TAKEAWAYS</span>
            <span>Engineering Principles</span>
          </div>
          <ul class="feature-list" style="margin-top: 8px;">
            <li><span class="icon">&bull;</span> <strong>Tailored Beats Generic:</strong> Bespoke architecture captures the exact operational nuance &mdash; from fuel logs to cash payroll.</li>
            <li><span class="icon">&bull;</span> <strong>WordPress as an Application Framework:</strong> Stripped of page-builders, WordPress provides an exceptional auth, ORM, and database backbone.</li>
            <li><span class="icon">&bull;</span> <strong>Internal Controls = Real Cash:</strong> Software must safeguard margins, enforce separation of duties, and maintain immutable auditability.</li>
          </ul>
        </div>

        <div class="card glow-gold" style="background: linear-gradient(135deg, #0A272B 0%, #0F383E 100%);">
          <div style="font-size: 10px; font-family: var(--font-mono); font-weight: 800; color: var(--accent-gold); letter-spacing: 1px; margin-bottom: 2px;">DEVELOPED BY</div>
          <div style="font-size: 22px; font-weight: 800; color: #FFF; margin-bottom: 2px;">3s-Soft</div>
          <div style="font-size: 13px; color: var(--primary); margin-bottom: 8px;"><strong>Jashedul Islam Shaun</strong> &bull; Founder &amp; Lead Systems Architect</div>
          
          <div style="font-size: 12px; color: var(--text-light); display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px;">
            <div>🌐 <strong>Web:</strong> 3s-soft.com</div>
            <div>💼 <strong>Client:</strong> KeyToBD (Kaisar Hamid Apon)</div>
            <div>🚢 <strong>Live Platform:</strong> bhelahouseboat.com</div>
          </div>

          <div style="background: var(--accent-coral); color: #FFF; padding: 8px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-align: center;">
            Need a custom booking platform or ERP engine for your business? Connect with 3s-Soft today.
          </div>
        </div>
      </div>
    </div>

    <footer class="slide-ftr">
      <div class="ftr-left">
        <span>bhelahouseboat.com</span>
        <span class="ftr-dot">&bull;</span>
        <span>Built by 3s-Soft</span>
      </div>
      <div class="ftr-page">Slide 10 / 10</div>
    </footer>
  </section>

</body>
</html>
"""

    with open(DECK_HTML, "w", encoding="utf-8") as f:
        f.write(html)
    print(f"[OK] Wrote upgraded HTML template: {DECK_HTML}")


def generate_pdf():
    build_deck_html()
    
    browser = find_browser()
    if not browser:
        print("[ERROR] Browser not found.")
        sys.exit(1)
        
    html_url = "file:///" + os.path.abspath(DECK_HTML).replace("\\", "/")
    cmd = [
        browser,
        "--headless=new",
        "--disable-gpu",
        "--run-all-compositor-stages-before-draw",
        "--no-pdf-header-footer",
        "--virtual-time-budget=6000",
        f"--print-to-pdf={OUT_PDF}",
        html_url
    ]
    
    print(f"Compiling PDF with: {browser}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode == 0 and os.path.exists(OUT_PDF):
        size_kb = os.path.getsize(OUT_PDF) / 1024
        print(f"[SUCCESS] Compiled Ultra-Premium PDF: {OUT_PDF} ({size_kb:.1f} KB)")
    else:
        print(f"[ERROR] Failed: {result.stderr}")
        sys.exit(1)


def generate_markdown():
    md_content = """# Case Study: Engineering an Autonomous Booking & ERP Operating System for Luxury Houseboat Tourism

![BHELA Houseboat Exterior](../../themes/bhela/assets/images/boat/exterior-1.jpg)

**Client:** BHELA – The Haor Exclusive (KeyToBD)  
**Industry:** Experiential Luxury Tourism & Hospitality  
**Location:** Tanguar Haor, Sunamganj, Bangladesh  
**Engineering Firm:** 3s-Soft (Jashedul Islam Shaun, Founder)  
**Live Platform:** [bhelahouseboat.com](https://bhelahouseboat.com)  
**Stack:** Custom WordPress Monorepo (PHP 8+, Vanilla JS ES6+, Custom Booking & ERP Plugins)  

---

## 🚀 Executive Summary

When running a luxury houseboat on a remote wetland like Tanguar Haor, operational reality is complex: high-value guest bookings, dynamic seasonal pricing, river-market grocery sourcing, diesel fuel volatility, cook/crew per-trip wages, and mobile money transactions (bKash/Nagad).

Standard hotel SaaS platforms (like Cloudbeds or Guesty) fail because they charge heavy monthly subscriptions ($150-$300/mo + 2-3% booking cuts) while ignoring offline operations like fuel logs, cash advances, and boat maintenance.

**3s-Soft engineered an end-to-end, zero-bloat custom WordPress monorepo** featuring:
1. **Midnight Monsoon Luxury Theme:** High-performance, zero page-builder overhead, sub-second TTFB, 74% image compression.
2. **Smart Dynamic Booking Engine:** Real-time cabin & charter availability, multi-tier age pricing, OTP SMS verification, and cryptographic invoice sharing.
3. **Enterprise ERP Suite:** 3-tier financial governance (Prepare → Check → Approve), locked cost sheets, automated monthly/yearly statements, dynamic crew payroll, and an immutable asset & inventory register with zero `DELETE` capability.

![Tanguar Haor Scenic Cruise](../../themes/bhela/assets/images/hero/hero-haor.jpg)

---

## ⚡ The Pre-System Operational Crisis (The "Before" State)

1. **Booking Friction & Double-Booking Risks:** Inquiries were managed over phone calls and Facebook DMs. Quotes were calculated manually from memory, leading to errors in holiday rates and children pricing.
2. **The Profitability Blindspot:** A cruise could sail completely full and still lose money due to surging diesel prices or untracked market expenses.
3. **3-Day Month-End Agony:** Month-end financial reconciliation took 3 full days of manual data entry from paper receipts and WhatsApp logs.
4. **Zero Separation of Duties & Inventory Shrinkage:** Staff spending cash also approved their own costs. Blankets, kitchen equipment, and engine spares disappeared with zero accountability.

---

## 🛠️ The Architecture & Engineered Modules

![Luxury Cabin Interior](../../themes/bhela/assets/images/cabins/cabin-3.jpg)

### 1. The High-Performance Front End
- Built with pure semantic PHP and Vanilla JS (zero jQuery, zero Elementor).
- Real-time quote estimator dynamically pulling holiday and weekend rules directly from the database.
- Mobile-first responsive booking wizard with SMS OTP verification (BulkSMSBD) to stop spam.

### 2. The 3-Tier Financial ERP Engine
- **3 Named Governance Steps:** `Prepare` (Crew) → `Check` (Manager) → `Approve` (Owner).
- **Cryptographic Sheet Lock:** Once approved, cost sheets cannot be modified without an audited administrator unlock.
- **Mathematical Invariance:** July 2026 statement reproduced the owner's manual ledger to the exact Taka (**13 trips · 335 guests · ৳498,214 gross profit**). Yearly reports execute the monthly engine 12 times to prevent calculation drift.

![Rooftop Dining Deck](../../themes/bhela/assets/images/spots/spot-1.jpg)

### 3. Dual-Register Inventory & Asset Engine
- Immutable category-based asset codes (`BHELA-KIT-0001`, `BHELA-ENG-0012`).
- Auto carry-forward of last month's closing count to the new month.
- Condition breakdown: *Good, Repairable, Under Repair, Damaged, Scrapped*.
- Mandatory discrepancy explanations before month-closing.

### 4. Enterprise Security & Audit Trail
- 6 granular roles (*Administrator, Manager, Booking Staff, Cost Preparer, Cost Checker, Storekeeper*).
- **Append-Only Audit Trail:** Zero `DELETE` or `DROP` handlers in the codebase. Every mutation is permanently logged.
- **14 Headless CLI Automated Test Suites (`tests/run.php`)** testing security boundaries, accounting math, and WCAG AA contrast.

---

## 📊 Measurable Business Impact & ROI

| Metric | Before | After (BHELA Engine) |
|---|---|---|
| **Booking Leakage** | Frequent double-bookings & DM chaos | **100% Fixed** (Real-time availability lock) |
| **Month-End Reconciliation** | 3 days of manual spreadsheet assembly | **1 Click** (Instant automated statement) |
| **Recurring SaaS Cost** | $1,800 – $3,600 / year | **$0 / month** (100% Client Ownership) |
| **Cost & Inventory Visibility** | Lost receipts, untracked fuel | **100% Audited** (3-tier approval & locked sheets) |
| **Test Coverage** | None | **14 Automated CLI Regression Suites** |

---

## 📱 LinkedIn Post Copy (Ready to Publish)

```text
How we replaced 3 days of spreadsheet chaos with a 1-click ERP & Booking Engine for a luxury houseboat brand 🚢⚡

Running a luxury hospitality vessel on Tanguar Haor (Bangladesh's most pristine wetland) comes with serious logistical complexity:
→ 6 luxury cabins & private charter bookings
→ Dynamic weekend/holiday surge rates & multi-tier children pricing
→ Cash & mobile payments (bKash/Nagad)
→ Remote river-market provisioning, volatile diesel consumption, and crew trip wages
→ Blankets, engine spares, and kitchen inventory disappearing without audit trails

Most hotel SaaS platforms (like Cloudbeds or Guesty) charge $200+/month plus booking cuts, but fail completely when handling offline river logistics, cash advances, and diesel logs.

So at 3s-Soft, we engineered a bespoke, zero-bloat WordPress Monorepo:

🔹 The Guest Experience:
• Pure Vanilla JS/CSS (Zero Elementor, zero jQuery)
• Real-time availability lock & dynamic pricing estimator
• SMS OTP verification to stop spam bookings
• Cryptographic, timing-attack-safe invoice sharing for WhatsApp

🔹 The Custom ERP Engine:
• 3-Tier Governance: Prepare (Crew) → Check (Manager) → Approve & Lock (Owner)
• 100% Mathematical Precision: July 2026 reconciled to the exact Taka (13 trips, 335 guests, ৳498,214 gross)
• Carry-Forward Inventory Ledger with condition tracking (Good/Repairable/Damaged)
• Append-Only Audit Trail: Zero DELETE routes anywhere in the system
• 14 Automated CLI Regression Test Suites

The result?
✅ Double bookings eliminated completely
✅ Month-end accounting reduced from 3 days to 1 click
✅ $0/month in recurring SaaS toll
✅ 100% client source code ownership

Swipe through the carousel slides above to see the full architectural breakdown! 👆

---
💡 Building a custom booking engine, SaaS, or ERP system for your business? Let's connect!

#SoftwareEngineering #WebDevelopment #WordPress #ERP #SystemDesign #HospitalityTech #CaseStudy #BespokeSoftware #TechLeadership
```
"""
    with open(OUT_MD, "w", encoding="utf-8") as f:
        f.write(md_content)
    print(f"[OK] Generated Markdown: {OUT_MD}")


if __name__ == "__main__":
    generate_pdf()
    generate_markdown()

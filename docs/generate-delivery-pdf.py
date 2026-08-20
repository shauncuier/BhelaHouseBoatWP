# -*- coding: utf-8 -*-
"""Generate the BHELA project delivery & handover PDF (3s-Soft -> KeyToBD)."""

import os

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable, NextPageTemplate,
)

# Resolved from this script's own location. It used to name an absolute path into
# "bhela-house-boat" - a different LocalWP site that still exists - so regenerating
# wrote the PDF into the wrong checkout and the real one silently went stale.
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "BHELA-Project-Delivery.pdf")

# Midnight Monsoon brand palette
INK      = colors.HexColor("#0A2A2F")
INK2     = colors.HexColor("#0E3B41")
PRIMARY  = colors.HexColor("#137A74")
AQUA     = colors.HexColor("#6FC7BF")
CTA      = colors.HexColor("#FF7A3D")
GOLD     = colors.HexColor("#F5C97B")
SAND     = colors.HexColor("#F4EDE1")
CREAM    = colors.HexColor("#FBF8F2")
TEXT     = colors.HexColor("#22403E")
SOFT     = colors.HexColor("#5E7472")
LINE     = colors.HexColor("#D9E2E0")

DOC_REF   = "3SS-BHELA-DEL-2026-07"
DEL_DATE  = "21 July 2026"
END_DATE  = "21 August 2026"
PRICE     = "USD 200.00"
ADD_REF   = "3SS-BHELA-ADD-2026-08"
ADD_DATE  = "18 August 2026"
ADD_PRICE = "USD 250.00"
TOTAL     = "USD 450.00"
VER_DEL   = "v2.15.0"
VER_NOW   = "v2.29.1"

styles = getSampleStyleSheet()
S = {}
S['title'] = ParagraphStyle('title', parent=styles['Title'], fontName='Helvetica-Bold',
                            fontSize=26, leading=31, textColor=colors.white, alignment=TA_CENTER)
S['subtitle'] = ParagraphStyle('subtitle', parent=styles['Normal'], fontName='Helvetica',
                               fontSize=12.5, leading=18, textColor=AQUA, alignment=TA_CENTER)
S['covermeta'] = ParagraphStyle('covermeta', parent=styles['Normal'], fontName='Helvetica',
                                fontSize=9.5, leading=14, textColor=colors.white, alignment=TA_CENTER)
S['h1'] = ParagraphStyle('h1', parent=styles['Heading1'], fontName='Helvetica-Bold',
                         fontSize=16, leading=20, textColor=INK, spaceBefore=6, spaceAfter=8)
S['h2'] = ParagraphStyle('h2', parent=styles['Heading2'], fontName='Helvetica-Bold',
                         fontSize=11.5, leading=15, textColor=PRIMARY, spaceBefore=10, spaceAfter=4)
S['body'] = ParagraphStyle('body', parent=styles['Normal'], fontName='Helvetica',
                           fontSize=9.6, leading=14.2, textColor=TEXT, alignment=TA_LEFT, spaceAfter=5)
S['small'] = ParagraphStyle('small', parent=S['body'], fontSize=8.5, leading=12, textColor=SOFT)
S['cell'] = ParagraphStyle('cell', parent=S['body'], fontSize=9, leading=12.6, spaceAfter=0)
S['cellb'] = ParagraphStyle('cellb', parent=S['cell'], fontName='Helvetica-Bold')
S['cellw'] = ParagraphStyle('cellw', parent=S['cell'], textColor=colors.white,
                            fontName='Helvetica-Bold')
S['bullet'] = ParagraphStyle('bullet', parent=S['body'], leftIndent=11, bulletIndent=2,
                             spaceAfter=2.5)


def para(t, s='body'):
    return Paragraph(t, S[s])


def bullets(items):
    return [Paragraph(t, S['bullet'], bulletText='•') for t in items]


def feature_table(rows, w1=52*mm):
    """Two-column feature table: capability | what it does."""
    data = [[Paragraph('<b>Capability</b>', S['cellw']), Paragraph('<b>What it does</b>', S['cellw'])]]
    for a, b in rows:
        data.append([Paragraph(a, S['cellb']), Paragraph(b, S['cell'])])
    t = Table(data, colWidths=[w1, 168*mm - w1], repeatRows=1)
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), INK),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LEFTPADDING', (0, 0), (-1, -1), 7),
        ('RIGHTPADDING', (0, 0), (-1, -1), 7),
        ('LINEBELOW', (0, 1), (-1, -1), 0.4, LINE),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, CREAM]),
        ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ]))
    return t


def info_table(rows):
    data = [[Paragraph(a, S['cellb']), Paragraph(b, S['cell'])] for a, b in rows]
    t = Table(data, colWidths=[46*mm, 122*mm])
    t.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4.5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4.5),
        ('LEFTPADDING', (0, 0), (-1, -1), 7),
        ('LINEBELOW', (0, 0), (-1, -2), 0.4, LINE),
        ('BACKGROUND', (0, 0), (0, -1), SAND),
        ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ]))
    return t


def callout(text, bg=SAND, bar=CTA):
    inner = Table([[Paragraph(text, S['cell'])]], colWidths=[164*mm])
    inner.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), bg),
        ('LEFTPADDING', (0, 0), (-1, -1), 9),
        ('RIGHTPADDING', (0, 0), (-1, -1), 9),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 7),
        ('LINEBEFORE', (0, 0), (0, -1), 3, bar),
    ]))
    return inner


def section(n, title):
    return Paragraph('%s.&nbsp;&nbsp;%s' % (n, title), S['h1'])


# ---------------- page furniture ----------------
def cover_page(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(INK2)
    canvas.rect(0, 0, w, h, stroke=0, fill=1)
    canvas.setFillColor(CTA)
    canvas.rect(0, h - 12*mm, w, 12*mm, stroke=0, fill=1)
    # footer rule
    canvas.setStrokeColor(colors.Color(1, 1, 1, alpha=0.18))
    canvas.setLineWidth(0.6)
    canvas.line(25*mm, 26*mm, w - 25*mm, 26*mm)
    canvas.setFillColor(AQUA)
    canvas.setFont('Helvetica', 8)
    canvas.drawString(25*mm, 20*mm, 'Document ref: %s' % DOC_REF)
    canvas.drawRightString(w - 25*mm, 20*mm, '3s-Soft  |  3s-soft.com')
    canvas.restoreState()


def body_page(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(CREAM)
    canvas.rect(0, 0, w, h, stroke=0, fill=1)
    # header band
    canvas.setFillColor(INK)
    canvas.rect(0, h - 16*mm, w, 16*mm, stroke=0, fill=1)
    canvas.setFillColor(colors.white)
    canvas.setFont('Helvetica-Bold', 9)
    canvas.drawString(21*mm, h - 10.6*mm, 'BHELA - The Haor Exclusive')
    canvas.setFillColor(AQUA)
    canvas.setFont('Helvetica', 8)
    canvas.drawRightString(w - 21*mm, h - 10.6*mm, 'Project Delivery & Handover')
    # footer
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.6)
    canvas.line(21*mm, 15*mm, w - 21*mm, 15*mm)
    canvas.setFillColor(SOFT)
    canvas.setFont('Helvetica', 7.6)
    canvas.drawString(21*mm, 10.5*mm, '3s-Soft  |  %s' % DOC_REF)
    canvas.drawRightString(w - 21*mm, 10.5*mm, 'Page %d' % (doc.page - 1))
    canvas.restoreState()


doc = BaseDocTemplate(OUT, pagesize=A4, title='BHELA - Project Delivery & Handover',
                      author='3s-Soft', subject='Project delivery, handover and support terms',
                      leftMargin=21*mm, rightMargin=21*mm, topMargin=24*mm, bottomMargin=20*mm)
cover_frame = Frame(25*mm, 30*mm, A4[0] - 50*mm, A4[1] - 70*mm, id='cover')
body_frame = Frame(21*mm, 18*mm, A4[0] - 42*mm, A4[1] - 42*mm, id='body')
doc.addPageTemplates([
    PageTemplate(id='cover', frames=[cover_frame], onPage=cover_page),
    PageTemplate(id='body', frames=[body_frame], onPage=body_page),
])

st = []

# ============ COVER ============
st.append(Spacer(1, 26*mm))
st.append(Paragraph('P R O J E C T &nbsp; D E L I V E R Y', S['subtitle']))
st.append(Spacer(1, 7*mm))
st.append(Paragraph('BHELA', S['title']))
st.append(Paragraph('The Haor Exclusive', ParagraphStyle(
    'ct', parent=S['title'], fontSize=15, leading=20, textColor=GOLD)))
st.append(Spacer(1, 5*mm))
st.append(Paragraph('Custom WordPress Booking Platform<br/>Delivery &amp; Handover Document', S['subtitle']))
st.append(Spacer(1, 14*mm))

cover_rows = [
    ['Developer', 'Client'],
    ['3s-Soft', 'KeyToBD'],
    ['Jashedul Islam Shaun<br/><font size=8 color="#6FC7BF">Founder</font>',
     'Kaisar Hamid Apon<br/><font size=8 color="#6FC7BF">Owner</font>'],
    ['3s-soft.com', 'bhelahouseboat.com'],
]
ct = Table([
    [Paragraph('<font color="#6FC7BF" size=8>DEVELOPER</font>', S['covermeta']),
     Paragraph('<font color="#6FC7BF" size=8>CLIENT</font>', S['covermeta'])],
    [Paragraph('<b><font size=13 color="white">3s-Soft</font></b>', S['covermeta']),
     Paragraph('<b><font size=13 color="white">KeyToBD</font></b>', S['covermeta'])],
    [Paragraph('Jashedul Islam Shaun<br/><font size=8 color="#9FBFBC">Founder</font>', S['covermeta']),
     Paragraph('Kaisar Hamid Apon<br/><font size=8 color="#9FBFBC">Owner</font>', S['covermeta'])],
], colWidths=[75*mm, 75*mm])
ct.setStyle(TableStyle([
    ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ('TOPPADDING', (0, 0), (-1, -1), 5),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LINEAFTER', (0, 0), (0, -1), 0.6, colors.Color(1, 1, 1, alpha=0.22)),
]))
st.append(ct)
st.append(Spacer(1, 16*mm))
st.append(HRFlowable(width='40%', thickness=1, color=CTA, hAlign='CENTER'))
st.append(Spacer(1, 6*mm))
st.append(Paragraph('Delivery date: <b>%s</b><br/>Includes 1 month free service &amp; review period '
                    '- valid until <b><font color="#F5C97B">%s</font></b>'
                    % (DEL_DATE, END_DATE), S['covermeta']))

st.append(NextPageTemplate('body'))  # every page after the cover uses the light template
st.append(PageBreak())
st.append(Spacer(1, 2*mm))

# ============ 1. SUMMARY ============
st.append(section(1, 'Project Summary'))
st.append(para(
    'BHELA - The Haor Exclusive is a custom WordPress booking platform built for a premium '
    'six-cabin houseboat operating on Tanguar Haor, Sunamganj, Bangladesh. The platform replaces '
    'phone-only and social-media-only booking with a professional website that takes bookings '
    'around the clock, prices them automatically, issues invoices, and gives the owner a single '
    'dashboard to manage the entire operation.'))
st.append(para(
    'The system is delivered as two purpose-built components - a bespoke theme and a booking '
    'engine plugin - written specifically for this business. No page builder, no rented platform, '
    'and no per-booking commission. The client owns the source code outright.'))
st.append(Spacer(1, 3*mm))
st.append(info_table([
    ('Project', 'BHELA - The Haor Exclusive : Custom WordPress Booking Platform'),
    ('Live website', 'bhelahouseboat.com'),
    ('Developer', '3s-Soft - Jashedul Islam Shaun, Founder (3s-soft.com)'),
    ('Client', 'KeyToBD - Kaisar Hamid Apon, Owner'),
    ('Delivery date', DEL_DATE),
    ('Free service until', '%s (see section 6)' % END_DATE),
    ('Version at delivery', 'Theme %s  |  Booking Engine %s' % (VER_DEL, VER_DEL)),
    ('Version now', 'Theme %s  |  Booking Engine %s (see Addendum A)' % (VER_NOW, VER_NOW)),
    ('Post-delivery development', '%s - new modules beyond original scope' % ADD_PRICE),
    ('Revised total', '%s' % TOTAL),
    ('Components', 'BHELA Theme v2.15.0 &nbsp;|&nbsp; BHELA Booking Engine Plugin v2.15.0'),
    ('Document ref', DOC_REF),
]))

# ============ 2. FEATURES ============
st.append(Spacer(1, 6*mm))
st.append(section(2, 'Delivered Features'))
st.append(para('The following capabilities are complete, tested and live.'))

st.append(Paragraph('2.1&nbsp;&nbsp;Website &amp; Design', S['h2']))
st.append(feature_table([
    ('Custom design', 'Bespoke "Midnight Monsoon" dark-teal luxury design system built for this brand - not a purchased template.'),
    ('Complete page set', 'Home, Cabins &amp; Rates, Trip Schedule, Food Menu, Gallery, FAQ, Booking Policies, Book Now, Contact and Blog - created automatically on activation.'),
    ('Bangla-first content', 'The entire guest-facing experience is written in Bangla, with English keywords where useful.'),
    ('Mobile-first', 'Fully responsive with a dedicated mobile action bar, touch-friendly navigation and optimised mobile booking flow.'),
    ('Editable content', 'Five Customizer panels let the owner change contact details, homepage text, all photos, tracking IDs and custom code - without a developer.'),
    ('Contact page &amp; form', 'A ready contact page with quick-contact cards, address and hours, social icons and a contact form that emails the owner.'),
    ('Page-builder ready', 'Elementor-compatible: any page can be rebuilt visually later without breaking the theme.'),
]))

st.append(Spacer(1, 4*mm))
st.append(Paragraph('2.2&nbsp;&nbsp;Booking Engine', S['h2']))
st.append(feature_table([
    ('Owner dashboard', 'A single overview screen: bookings by status, revenue and money collected, upcoming trips, recent activity, a setup checklist and one-click actions.'),
    ('Booking wizard', 'Guided multi-step form with live price calculation as the guest selects dates, cabins and guests.'),
    ('Six-cabin inventory', 'Five cabin types across six cabins, each with its own per-person rate and sharing capacity.'),
    ('Smart pricing', 'Automatic weekday, weekend and holiday rates with a weekday discount of up to 20%.'),
    ('Children pricing', 'Applied automatically: ages 9+ full rate; ages 4-8 a flat per-child fee (default Tk 5,000, no weekday discount); ages 0-4 free.'),
    ('Live availability', 'Availability updates itself the moment a booking is confirmed - every manager and the public schedule see the same live count, which prevents overbooking.'),
    ('Booking management', 'Full admin screen for every booking, plus manual entry for phone and walk-in guests.'),
    ('Status workflow', 'Pending, Advance Paid, Confirmed, Completed and Cancelled - with automatic guest notification on confirmation.'),
    ('Guest self-service', 'Guests can track their own booking status using their phone number or email address.'),
    ('Discount panel', 'Percentage, flat or custom counter-offer pricing for negotiated bookings.'),
    ('Trip calendar', 'Departure schedule with automatic start/end dates, labels and duration, a per-trip holiday toggle, booked-cabin counts and automatic "Full Booked" status.'),
    ('Photo gallery', 'Category-filtered gallery with one-click bulk upload of many photos at once.'),
    ('Guest reviews', 'Star-rated reviews managed from the dashboard and displayed on the website.'),
    ('Activity log', 'A plain-language record of bookings, emails, SMS, trip and settings changes so the owner can confirm everything worked.'),
    ('WhatsApp integration', 'One-tap WhatsApp contact with the booking details pre-filled.'),
]))

st.append(PageBreak())
st.append(Spacer(1, 2*mm))

st.append(Paragraph('2.3&nbsp;&nbsp;Invoicing &amp; Notifications', S['h2']))
st.append(feature_table([
    ('Automatic invoices', 'Every booking generates a branded, print-ready invoice with a per-person cost breakdown, advance, paid and due amounts.'),
    ('Secure invoice links', 'Each invoice link carries a private signed key, so it is safe to send by WhatsApp or email.'),
    ('Payment details', 'bKash, Nagad, bank transfer and QR details are pulled from settings into every invoice.'),
    ('Email notifications', 'Automatic email to the owner on every new booking, and to the guest on request and on confirmation - each individually switchable.'),
    ('Email controls', 'Custom sender name, reply-to address, owner notification address and a one-click test email.'),
    ('SMS notifications', 'Optional SMS on new booking and status change, with editable Bangla templates and auto-filled placeholders.'),
    ('Any SMS provider', 'BulkSMSBD preset plus a custom gateway option - a different provider can be configured without any code change.'),
]))

st.append(Spacer(1, 4*mm))
st.append(Paragraph('2.4&nbsp;&nbsp;Search Engine Optimisation &amp; Performance', S['h2']))
st.append(feature_table([
    ('On-page SEO', 'Per-page meta descriptions, Open Graph and Twitter cards, canonical URLs and correct Bangla language signals.'),
    ('Structured data', 'A connected JSON-LD graph - Organization, Website, Local Business, Tourist Attraction, Breadcrumbs, Articles, FAQ and aggregate guest rating.'),
    ('Search visibility', 'XML sitemap and robots.txt configured, with Google Search Console set up and the sitemap submitted.'),
    ('Speed optimisation', 'Theme image payload reduced by approximately 74%, font preconnect, lazy loading and layout-shift protection.'),
    ('Lean codebase', 'Single stylesheet, no jQuery and no page-builder bloat - fast by construction on mobile networks.'),
    ('Content blog', 'A ready travel blog with categories, tags, related posts, reading time and a booking call-to-action on every article.'),
]))

st.append(Spacer(1, 4*mm))
st.append(Paragraph('2.5&nbsp;&nbsp;Analytics, Custom Code &amp; Security', S['h2']))
st.append(feature_table([
    ('Analytics ready', 'Google Analytics 4 and Meta (Facebook) Pixel enabled by pasting the ID only. The owner\'s own admin visits are excluded from statistics.'),
    ('Custom code panel', 'Three boxes to inject any code into the page head, immediately after the body tag, or in the footer - no theme file editing required.'),
    ('Form protection', 'Hidden honeypot field and per-visitor rate limiting stop automated spam submissions.'),
    ('Request verification', 'All form and admin actions are protected with WordPress security tokens and capability checks.'),
    ('Private guest data', 'Booking records are stored privately and are not exposed through any public URL or API.'),
    ('Credential safety', 'API keys are stored masked and are never displayed or written to logs.'),
]))

st.append(PageBreak())
st.append(Spacer(1, 2*mm))

# ============ 3. TECH SPEC ============
st.append(section(3, 'Technical Specification'))
st.append(info_table([
    ('Platform', 'WordPress (custom theme and custom plugin)'),
    ('Requirements', 'WordPress 6.0 or newer, PHP 8.0 or newer'),
    ('BHELA Theme', 'Version 2.15.0 - design, pages, blog, SEO, analytics, custom code, contact page'),
    ('Booking Engine', 'Version 2.15.0 - dashboard, bookings, pricing, invoices, trips, gallery, reviews, activity log, email and SMS'),
    ('Front-end', 'Vanilla JavaScript and CSS - no jQuery, no build step, no external framework'),
    ('Source control', 'Full Git history hosted on GitHub, released with version tags'),
    ('Third-party services', 'FluentSMTP for email delivery; SMS gateway optional'),
]))

# ============ 4. HANDOVER ============
st.append(Spacer(1, 6*mm))
st.append(section(4, 'Deliverables &amp; Handover'))
st.append(para('The following items are handed over to the client on delivery:'))
st.extend(bullets([
    '<b>Complete source code</b> for the custom theme and booking engine plugin, with full version history on GitHub.',
    '<b>Installable packages</b> - theme and plugin ZIP files ready for deployment to any WordPress host.',
    '<b>Live, configured website</b> at bhelahouseboat.com with all pages, menus and settings in place.',
    '<b>Owner\'s Manual</b> - a plain-language, Bangla-friendly guide covering every day-to-day task: bookings, invoices, rates, availability, blog, email, SMS, analytics and settings.',
    '<b>Project overview documentation</b> describing the platform, its value and its architecture.',
    '<b>Production go-live checklist</b> covering caching, HTTPS, email deliverability, Search Console and Google Business Profile.',
    '<b>Google Analytics 4 and Google Search Console</b> - both accounts created, verified and connected to the website, with the sitemap submitted to Google.',
    '<b>All passwords and login credentials</b> - WordPress administrator account, hosting control panel, domain registrar, database, Google Analytics, Search Console and any service accounts created for this project are handed over to the client in full.',
    '<b>Full ownership</b> - the client owns the delivered code and all site data outright, with no licence fee, no lock-in and no commission on bookings.',
]))
st.append(Spacer(1, 2*mm))
st.append(callout(
    '<b>Credentials handover.</b> The client receives every username and password associated with '
    'the project. 3s-Soft retains no exclusive access, and the client can transfer the site to any '
    'other developer or host at any time.', colors.HexColor('#EAF3F2'), PRIMARY))
st.append(Spacer(1, 2*mm))
st.append(callout(
    '<b>No recurring cost to 3s-Soft.</b> Once delivered, the platform runs without any subscription '
    'or per-booking fee payable to the developer. The client is responsible only for their own '
    'hosting, domain and any optional third-party services such as SMS credits.', SAND, PRIMARY))

# ============ 5. COMMERCIAL ============
st.append(Spacer(1, 6*mm))
st.append(section(5, 'Commercial Terms'))

pt = Table([
    [Paragraph('<b>Description</b>', S['cellw']), Paragraph('<b>Amount</b>', S['cellw'])],
    [Paragraph('Design, development and delivery of the BHELA custom WordPress booking platform '
               '(theme and booking engine), including deployment support and one month of free '
               'service as set out in section 6.', S['cell']),
     Paragraph('USD 200.00', S['cellb'])],
    [Paragraph('Domain setup, hosting setup, WordPress installation and configuration, plus '
               'Google Analytics and Google Search Console setup - see section 5.1.', S['cell']),
     Paragraph('<font color="#137A74">No charge</font>', S['cellb'])],
    [Paragraph('<b>Total project price</b>', S['cellb']),
     Paragraph('<b>USD 200.00</b>', S['cellb'])],
], colWidths=[128*mm, 40*mm])
pt.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), INK),
    ('BACKGROUND', (0, -1), (-1, -1), SAND),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('ALIGN', (1, 0), (1, -1), 'RIGHT'),
    ('TOPPADDING', (0, 0), (-1, -1), 6),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
    ('RIGHTPADDING', (0, 0), (-1, -1), 7),
    ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ('LINEBELOW', (0, 1), (-1, -2), 0.4, LINE),
    ('LINEABOVE', (0, -1), (-1, -1), 0.9, PRIMARY),
]))
st = st[:-1]  # re-add section 5 heading inside the keep-together block
st.append(KeepTogether([
    section(5, 'Commercial Terms'),
    pt,
    Spacer(1, 3*mm),
    para('<b>Total project price: %s (Two Hundred US Dollars).</b> The price is inclusive of '
         'everything listed in sections 2 and 4 of this document, and of the one month free '
         'service period described in section 6.' % PRICE),
]))

# ---- 5.1 free-of-charge value ----
free_rows = [
    ('Domain setup and DNS configuration', 'USD 15.00'),
    ('Hosting setup and site deployment', 'USD 25.00'),
    ('WordPress installation and full configuration', 'USD 35.00'),
    ('Google Analytics 4 setup and verification', 'USD 20.00'),
    ('Google Search Console setup and sitemap submission', 'USD 20.00'),
    ('One month service and support period (section 6)', 'USD 50.00'),
]
fdata = [[Paragraph('<b>Provided free of charge</b>', S['cellw']),
          Paragraph('<b>Standard value</b>', S['cellw'])]]
for a, b in free_rows:
    fdata.append([Paragraph(a, S['cell']), Paragraph('<font color="#5E7472">%s</font>' % b, S['cell'])])
fdata.append([Paragraph('<b>Total value received free</b>', S['cellb']),
              Paragraph('<b><font color="#137A74">USD 165.00</font></b>', S['cellb'])])
ft = Table(fdata, colWidths=[128*mm, 40*mm])
ft.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), PRIMARY),
    ('BACKGROUND', (0, -1), (-1, -1), colors.HexColor('#EAF3F2')),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('ALIGN', (1, 0), (1, -1), 'RIGHT'),
    ('TOPPADDING', (0, 0), (-1, -1), 5.5),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 5.5),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
    ('RIGHTPADDING', (0, 0), (-1, -1), 7),
    ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ('LINEBELOW', (0, 1), (-1, -2), 0.4, LINE),
    ('LINEABOVE', (0, -1), (-1, -1), 0.9, PRIMARY),
]))

summary = Table([
    [Paragraph('<font color="#5E7472" size=8>TOTAL VALUE DELIVERED</font><br/>'
               '<b><font size=13 color="#0A2A2F">USD 365.00</font></b>', S['cell']),
     Paragraph('<font color="#5E7472" size=8>AMOUNT CHARGED</font><br/>'
               '<b><font size=13 color="#0A2A2F">USD 200.00</font></b>', S['cell']),
     Paragraph('<font color="#5E7472" size=8>CLIENT RECEIVES FREE</font><br/>'
               '<b><font size=13 color="#137A74">USD 165.00</font></b>', S['cell'])],
], colWidths=[56*mm, 56*mm, 56*mm])
summary.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, -1), SAND),
    ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ('INNERGRID', (0, 0), (-1, -1), 0.5, colors.white),
    ('TOPPADDING', (0, 0), (-1, -1), 8),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
    ('LEFTPADDING', (0, 0), (-1, -1), 9),
    ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
]))

st.append(Spacer(1, 6*mm))
st.append(KeepTogether([
    Paragraph('5.1&nbsp;&nbsp;Additional Services Provided Free of Charge', S['h2']),
    para('The following work was carried out by 3s-Soft at no cost to the client. It is <b>not</b> '
         'included in the price above and is listed here at its standard market value so the client '
         'can see the full scope of what has been delivered.'),
    ft,
    Spacer(1, 4*mm),
    summary,
    Spacer(1, 3*mm),
    para('Any third-party fees payable directly to providers - domain registration, hosting plan '
         'and SMS credits - remain the client\'s own cost and are not part of this document.',
         'small'),
]))

st.append(PageBreak())
st.append(Spacer(1, 2*mm))

# ============ 6. SUPPORT ============
st.append(section(6, 'One Month Free Service &amp; Review Period'))
st.append(para(
    'A <b>one month free service and review period</b> is included with this delivery at no '
    'additional cost. It allows the client to use the platform in real operating conditions and '
    'raise anything that does not work as intended.'))
st.append(Spacer(1, 2*mm))
st.append(info_table([
    ('Period', '%s to %s (one calendar month from delivery)' % (DEL_DATE, END_DATE)),
    ('Cost', 'Free - included in the project price'),
    ('Raise an issue via', 'Direct contact with 3s-Soft (Jashedul Islam Shaun)'),
]))
st.append(Spacer(1, 4*mm))

# Prominent deadline strip
deadline = Table([[
    Paragraph('<font color="#9FBFBC" size=8>PERIOD STARTS</font><br/>'
              '<b><font size=12 color="white">%s</font></b>' % DEL_DATE, S['cell']),
    Paragraph('<font color="#F5C97B" size=8>DEADLINE - FREE SERVICE PERIOD ENDS</font><br/>'
              '<b><font size=15 color="#FF7A3D">%s</font></b>' % END_DATE, S['cell']),
]], colWidths=[56*mm, 112*mm])
deadline.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, -1), INK),
    ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ('TOPPADDING', (0, 0), (-1, -1), 10),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 10),
    ('LEFTPADDING', (0, 0), (-1, -1), 11),
    ('LINEAFTER', (0, 0), (0, -1), 0.7, colors.Color(1, 1, 1, alpha=0.22)),
    ('BOX', (0, 0), (-1, -1), 0.5, INK),
]))
st.append(deadline)
st.append(Spacer(1, 2*mm))
st.append(para('<b>Please report any issue on or before %s.</b> Anything raised within the period '
               'is fixed free of charge. After this date the free service period expires and '
               'further work is chargeable or arranged by mutual agreement.' % END_DATE))
st.append(Spacer(1, 3*mm))

st.append(Paragraph('6.1&nbsp;&nbsp;What is covered', S['h2']))
st.extend(bullets([
    '<b>Any bug or defect found in the delivered scope will be fixed free of charge.</b> If something '
    'in this document does not work as described, 3s-Soft will correct it at no cost.',
    'Errors in booking calculation, invoices, notifications, availability or any delivered feature.',
    'Display or layout problems on desktop and mobile devices.',
    'Configuration assistance and guidance on using the dashboard.',
    'Minor settings and content adjustments requested by the client.',
]))

st.append(Spacer(1, 2*mm))
st.append(Paragraph('6.2&nbsp;&nbsp;What is not covered', S['h2']))
st.extend(bullets([
    'New features or changes beyond the scope delivered in section 2 - these can be quoted separately.',
    'A redesign or restructure of the website.',
    'Third-party costs such as hosting, domain renewal, SMS credits or paid plugins.',
    'Content writing, photography or translation.',
    'Problems caused by changes made by the client or another party to the code, or by installing '
    'third-party plugins that conflict with the platform.',
]))

st.append(Spacer(1, 3*mm))
st.append(callout(
    '<b>Commitment.</b> During the review period, if the client finds any issue in the delivered '
    'platform, 3s-Soft will fix it free of charge. The period ends on <b>%s</b>; after that date '
    'continued support and any new development can be arranged by mutual agreement.' % END_DATE, colors.HexColor('#FFF3E9'), CTA))

# ============ ADDENDUM A ============
st.append(PageBreak())
st.append(Paragraph('Addendum A &#8212; Development After Delivery', S['h1']))
st.append(para('<b>Period covered:</b> 22 July 2026 to %s &nbsp;&#183;&nbsp; <b>Document ref:</b> %s' % (ADD_DATE, ADD_REF)))
st.append(para('The platform was delivered on %s at version %s. In the four weeks since, it has been '
               'developed considerably further at the client\'s request, and separately corrected '
               'wherever something in the original delivery did not behave as documented. Those are two '
               'different things commercially, and this addendum keeps them apart.' % (DEL_DATE, VER_DEL)))
st.append(Spacer(1, 3*mm))
st.append(info_table([
    ('Section 7 - new modules beyond original scope', '<b>Chargeable. %s</b>' % ADD_PRICE),
    ('Section 8 - fixes inside the delivered scope', '<b>No charge</b>, under the section 6 free service period'),
]))
st.append(Spacer(1, 4*mm))
st.append(callout('<b>Scale of the work.</b> 35 tagged releases, 44 commits, 15 new plugin modules and '
                  'approximately 23,600 lines of new code across 85 files - taking the platform from '
                  '%s to %s.' % (VER_DEL, VER_NOW)))

st.append(Spacer(1, 7*mm))
st.append(section(7, 'New Modules Beyond Original Scope &#8212; Chargeable'))
st.append(para('None of the following appears in section 2 of the original delivery. Each is a new '
               'module built after handover.'))

st.append(Paragraph('7.1&nbsp;&nbsp;Accounting &amp; Financial Control Suite', S['h2']))
st.append(para('The largest single addition. The platform now answers <i>did this trip make money, and '
               'did this month?</i> rather than only <i>who booked?</i>'))
st.append(feature_table([
    ('Trip Cost Sheets', 'One sheet per trip covering every cost head - fuel, groceries, meat, fish, gas, jetty, staff transport, electricity and more - each with three payment columns, a remark and spare rows for one-offs. Cost heads are owner-editable, so the list follows the business rather than the code.'),
    ('Approval workflow', 'Prepare, Check, Approve - each step stamped with <b>who</b> did it and <b>when</b>. An approved sheet locks: figures can no longer be edited, and an administrator must deliberately unlock it before anything changes.'),
    ('Expenses', 'Spending not tied to a trip - advertising, boosting, renovation, one-off purchases - with date, type, amount, payment method, means of verification and remark. Types and payment methods are owner-editable.'),
    ('Monthly Statement', 'The month\'s <b>approved</b> trips as rows, then one deduction line per expense type, then staff payroll, then gross profit. Also cost per person and profit per person, with the three sign-offs carried through. Print and PDF ready.'),
    ('Yearly Report', 'Twelve monthly statements rolled up, in <b>financial-year (July to June)</b> or calendar mode, with season totals, margin and the year\'s expense mix. Built from the monthly statement twelve times, so a yearly figure can never disagree with the month it summarises.'),
    ('Staff roster &amp; payroll', 'Staff recorded as trip-based, monthly-salaried or both, with per-trip rate, monthly salary and payment account. A salary sheet per month prices trip-based crew automatically from the number of trips that actually ran, and records advances, settlement and adjustments.'),
    ('Payroll in the bottom line', 'Gross profit is now trip profit less expenses less payroll. Wages are a real cost and are treated as one.'),
]))

st.append(Paragraph('7.2&nbsp;&nbsp;Inventory &amp; Asset Register', S['h2']))
st.append(para('A complete stock and asset control system - the second major addition.'))
st.append(feature_table([
    ('Item register', 'A permanent record per item: category, sub-category, location, unit, brand, model, serial, supplier, purchase date and bill, warranty, unit value, responsible person, photos. Every item receives a <b>frozen Item ID</b> built from its category (BHELA-KIT-0001) that never changes and is never reused.'),
    ('Monthly stock sheet', 'Each month <b>opens on the previous month\'s closing</b>, automatically. Records what came in, what went out, what was used, lost or scrapped, and splits what is on board by condition: good, repairable, under repair, damaged.'),
    ('Physical verification', 'A counted figure per item against the system figure. Any variance must be explained before the month can be closed - a discrepancy is reported, never quietly corrected.'),
    ('Month close &amp; lock', 'A closed month is <b>genuinely closed</b>. Its figures cannot be edited through the form, cannot be changed directly in the database, and cannot be deleted - not even by an administrator. Reopening is possible and is itself recorded.'),
    ('Audit Trail', 'An append-only record of who changed which figure, what it was before, what it became and why. It has no delete path and no clear button, by design - it is the register\'s memory, not a log to tidy up.'),
    ('CSV importer', 'Four steps - upload, map your columns, dry run, commit. It reads the spreadsheet you already keep rather than demanding a fixed format, tells you what it would do before doing anything, blocks a row it cannot resolve instead of guessing, and is safe to run twice.'),
    ('Inventory &amp; Asset Reports', 'Two reports with print and CSV export - consumables by movement and value, assets by location and condition.'),
]))

st.append(Paragraph('7.3&nbsp;&nbsp;Staff Roles &amp; Access Control', S['h2']))
st.append(feature_table([
    ('Six staff roles', 'Manager, booking staff, cost preparer, cost checker and storekeeper, alongside the administrator - so office staff, kitchen and store can each be given their own login.'),
    ('Editable permissions', 'A Team screen with a checkbox per permission per role, saved from the page. Access is configured by the owner, not hard-coded.'),
    ('Role-aware admin', 'Each person sees only the menus they can actually use. A storekeeper sees the store; a cost preparer sees accounts.'),
]))

st.append(Paragraph('7.4&nbsp;&nbsp;Mobile Verification (OTP)', S['h2']))
st.append(feature_table([
    ('Verified numbers', 'Optional SMS code on the booking form, so a booking carries a number proven to work. Email fallback, with per-number and per-IP throttles.'),
    ('One-part messages', 'Codes are forced into GSM-7 so a verification SMS never silently costs double.'),
    ('SMS credit on dashboard', 'Live gateway balance with a low-balance warning, so credit never runs out mid-season unnoticed.'),
]))

st.append(Paragraph('7.5&nbsp;&nbsp;Operational Reporting &amp; Admin Redesign', S['h2']))
st.append(feature_table([
    ('Trip Report', 'Every booking for a date or range with advance, due and totals - printable, exportable to CSV, and shareable as a WhatsApp message for the crew.'),
    ('Admin design system', 'A single consistent design across every admin screen - headers, summary cards, ledger tables, status pills, print styles - replacing screen-by-screen styling.'),
    ('Four admin menus', 'The sidebar had grown to 22 rows under one Bookings menu. It is now four menus grouped by job - <b>Bookings, Accounts, Store, Setup</b> - each hidden from anyone who cannot use it. Old links and bookmarks continue to work.'),
    ('Full Boat bookings', 'Whole-boat reservations are bookable from the admin as well as the form, take all six cabins, and now arrive <b>priced automatically</b> at the standard 36-person rate for the owner to adjust after negotiating - instead of arriving blank.'),
]))

st.append(Paragraph('7.6&nbsp;&nbsp;Guest-Facing Additions', S['h2']))
st.append(feature_table([
    ('Reviews with photos', 'Guests submit star-rated reviews with trip photos after travelling; the owner moderates them, and approved reviews appear in a carousel on the website.'),
    ('Booking Guide page', 'A step-by-step guide for first-time houseboat guests, plus two-level primary navigation to carry the larger page set.'),
    ('Manageable Spots', 'The included and optional sightseeing spots are now editable content rather than fixed text.'),
]))

st.append(Paragraph('7.7&nbsp;&nbsp;Quality Assurance Programme', S['h2']))
st.append(para('Not a feature, and the reason the modules above can be trusted with money.'))
st.append(feature_table([
    ('Automated test suite', '<b>14 test harnesses</b>, run from a single command, covering pricing, the July statement reproduced to the taka, salary, cost sheets, the booking save path, the stock register, every admin screen, colour contrast, front-end behaviour behind a page cache, OTP, the SMS gateway, version consistency and the yearly rollup.'),
    ('What it is for', 'Every future change is checked against all of it before release. The suite has caught real defects - including several during this period - before they reached the live site.'),
]))

st.append(PageBreak())
st.append(section(8, 'Fixes Inside the Delivered Scope &#8212; No Charge'))
st.append(para('Everything below was corrected <b>free of charge</b> under the section 6 free service '
               'period, which runs to <b>%s</b>. Listed for completeness, so the client can see what '
               'was done.' % END_DATE))
st.append(Paragraph('8.1&nbsp;&nbsp;Booking, pricing and invoices', S['h2']))
st.extend(bullets([
    'Homepage price estimator corrected until it matched the booking engine exactly',
    'Weekend rate calculation corrected',
    'Guest count drift between the form, the admin and the invoice',
    'Advance amount made owner-owned, with correctly derived percentages',
    'Invoice discount row, note placeholders, contact details and footer duplication',
    'Taka symbol rendering fixed everywhere, including print and PDF',
    'Duplicate WhatsApp numbers and duplicate confirmation emails',
    '<b>Day type on invoices</b> - a booking moved to a weekday kept printing Weekend; the label is now derived when read rather than cached',
    '<b>PAID stamp</b> - a settled invoice now carries a clear paid mark, drawn as an outline so it survives printing with backgrounds switched off',
]))
st.append(Paragraph('8.2&nbsp;&nbsp;Availability, mobile and security', S['h2']))
st.extend(bullets([
    'Additive cabin availability (held plus sold), with a manual Booked status honoured across the engine',
    'Booking form made safe behind a page cache, and the real visitor IP detected correctly behind a CDN so rate limiting cannot bucket every guest together',
    'Trip schedule invisible on mobile; WhatsApp button intercepting touches on mobile',
    'Homepage image payload reduced with correctly sized renditions and hero preload',
    'Staff file-upload capability revoked where it was not needed',
    'Invoice links marked no-store and noindex, so they cannot be cached or indexed',
]))
st.append(Paragraph('8.3&nbsp;&nbsp;Defects found in the new modules and corrected before release', S['h2']))
st.append(para('Found by operating the platform with real data rather than test data, and fixed at no charge:'))
st.extend(bullets([
    'Stock valuation reading zero permanently, because the first save froze each item\'s unit value at zero',
    'A newly opened stock month reporting itself empty, above rows that clearly were not',
    'A closed stock month becoming unreachable after a maintenance routine cleared its index',
    'A cleared trips field paying a crew member for no trips, and under-deducting that month\'s payroll',
    'Hiring a staff member retroactively changing the payroll of months already paid',
    'Clicking Bookings opening the dashboard instead of the booking list',
]))

st.append(Spacer(1, 7*mm))
st.append(section('8A', 'Revised Commercial Terms'))
_ct = Table([
    [Paragraph('<b>Description</b>', S['cellw']), Paragraph('<b>Amount</b>', S['cellw'])],
    [Paragraph('Original project - design, development and delivery of the BHELA platform, per sections 2, 4 and 5', S['cell']),
     Paragraph(PRICE, S['cellb'])],
    [Paragraph('<b>Post-delivery development</b> - new modules beyond original scope, per section 7', S['cell']),
     Paragraph('<b>%s</b>' % ADD_PRICE, S['cellb'])],
    [Paragraph('Fixes inside the delivered scope, per section 8', S['cell']),
     Paragraph('No charge', S['cellb'])],
    [Paragraph('<b>Revised total</b>', S['cell']),
     Paragraph('<b>%s</b>' % TOTAL, S['cellb'])],
], colWidths=[128*mm, 40*mm])
_ct.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), INK),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('TOPPADDING', (0, 0), (-1, -1), 5),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
    ('RIGHTPADDING', (0, 0), (-1, -1), 7),
    ('LINEBELOW', (0, 1), (-1, -1), 0.4, LINE),
    ('BACKGROUND', (0, -1), (-1, -1), SAND),
    ('BOX', (0, 0), (-1, -1), 0.5, LINE),
]))
st.append(_ct)
st.append(Spacer(1, 4*mm))
st.append(para('<b>Post-delivery development: %s (Two Hundred and Fifty US Dollars).</b> This covers every '
               'module in section 7 - the accounting and financial control suite, the inventory and asset '
               'register, staff roles and access control, mobile verification, the operational reporting '
               'and admin redesign, the guest-facing additions, and the automated test suite.' % ADD_PRICE))
st.append(para('For context, the accounting suite and the inventory register would each ordinarily be '
               'quoted as a project in its own right. They are charged here as a single continuation of '
               'the original engagement.'))
st.append(Spacer(1, 3*mm))
_tot = Table([
    [Paragraph('Original project', S['small']), Paragraph('Post-delivery development', S['small']),
     Paragraph('Revised total', S['small'])],
    [Paragraph('<b><font size=13 color="#0A2A2F">%s</font></b>' % PRICE, S['cell']),
     Paragraph('<b><font size=13 color="#137A74">%s</font></b>' % ADD_PRICE, S['cell']),
     Paragraph('<b><font size=13 color="#0A2A2F">%s</font></b>' % TOTAL, S['cell'])],
], colWidths=[56*mm, 56*mm, 56*mm])
_tot.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), SAND),
    ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
    ('TOPPADDING', (0, 0), (-1, -1), 6),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ('INNERGRID', (0, 0), (-1, -1), 0.4, LINE),
]))
st.append(_tot)
st.append(Spacer(1, 4*mm))
st.append(para('Section 5.1 of this document still applies: domain setup, hosting setup, WordPress '
               'installation and configuration, Google Analytics and Google Search Console setup were all '
               'provided free of charge, at a standard value of USD 165.00. Third-party fees payable '
               'directly to providers - domain registration, hosting and SMS credits - remain the '
               'client\'s own cost and are not part of this document.'))
st.append(Spacer(1, 3*mm))
st.append(callout('<b>Support on the new modules.</b> Defects found in the section 7 modules were '
                  'corrected free of charge as they were built, and that continues to the end of the '
                  'original free service period on <b>%s</b>. Support and development after that date is '
                  'arranged by mutual agreement, as set out in section 6.' % END_DATE))

# ============ 9. ACCEPTANCE ============
st.append(Spacer(1, 8*mm))
_signhead = [
    section(9, 'Acceptance &amp; Sign-off'),
    para('By signing below, both parties confirm that the platform described in this document has '
         'been delivered and accepted, and that the commercial and support terms set out in '
         'sections 5, 6, 7, 8 and 8A - including Addendum A and the revised total of '
         '<b>%s</b> - are agreed.' % TOTAL),
    Spacer(1, 10*mm),
]

sign = Table([
    [Paragraph('<b>Developer</b>', S['cellb']), '', Paragraph('<b>Client</b>', S['cellb'])],
    [Paragraph('3s-Soft', S['cell']), '', Paragraph('KeyToBD', S['cell'])],
    [Spacer(1, 14*mm), '', Spacer(1, 14*mm)],
    [Paragraph('Jashedul Islam Shaun<br/><font size=8 color="#5E7472">Founder, 3s-Soft</font>', S['cell']), '',
     Paragraph('Kaisar Hamid Apon<br/><font size=8 color="#5E7472">Owner, KeyToBD</font>', S['cell'])],
    [Paragraph('Date: ______________________', S['small']), '',
     Paragraph('Date: ______________________', S['small'])],
], colWidths=[75*mm, 18*mm, 75*mm])
sign.setStyle(TableStyle([
    ('VALIGN', (0, 0), (-1, -1), 'BOTTOM'),
    ('TOPPADDING', (0, 0), (-1, -1), 3),
    ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
    ('LINEBELOW', (0, 2), (0, 2), 0.8, INK),
    ('LINEBELOW', (2, 2), (2, 2), 0.8, INK),
    ('BOTTOMPADDING', (0, 3), (-1, 3), 8),
]))
st.append(KeepTogether(_signhead + [sign]))

st.append(Spacer(1, 12*mm))
st.append(HRFlowable(width='100%', thickness=0.6, color=LINE))
st.append(Spacer(1, 3*mm))
st.append(Paragraph(
    'BHELA - The Haor Exclusive &nbsp;|&nbsp; Project delivery and handover document &nbsp;|&nbsp; %s<br/>'
    'Designed and developed by <b>3s-Soft</b> - 3s-soft.com &nbsp;|&nbsp; &#169; 2026 3s-Soft. All rights reserved.'
    % ('%s  |  Addendum A %s (%s)' % (DOC_REF, ADD_REF, ADD_DATE)), S['small']))

doc.build(st)
print('PDF written: %s' % OUT)

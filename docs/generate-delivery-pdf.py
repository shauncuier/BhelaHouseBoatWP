# -*- coding: utf-8 -*-
"""Generate the BHELA project delivery & handover PDF (3s-Soft -> KeyToBD)."""

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable, NextPageTemplate,
)

OUT = r"C:\Users\jashe\Local Sites\bhela-house-boat\app\public\wp-content\docs\BHELA-Project-Delivery.pdf"

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
    ('Components', 'BHELA Theme v2.8.0 &nbsp;|&nbsp; BHELA Booking Engine Plugin v2.6.3'),
    ('Document ref', DOC_REF),
]))

# ============ 2. FEATURES ============
st.append(Spacer(1, 6*mm))
st.append(section(2, 'Delivered Features'))
st.append(para('The following capabilities are complete, tested and live.'))

st.append(Paragraph('2.1&nbsp;&nbsp;Website &amp; Design', S['h2']))
st.append(feature_table([
    ('Custom design', 'Bespoke "Midnight Monsoon" dark-teal luxury design system built for this brand - not a purchased template.'),
    ('Complete page set', 'Home, Cabins &amp; Rates, Trip Schedule, Food Menu, Gallery, FAQ, Booking Policies, Book Now, and Blog - created automatically on activation.'),
    ('Bangla-first content', 'The entire guest-facing experience is written in Bangla, with English keywords where useful.'),
    ('Mobile-first', 'Fully responsive with a dedicated mobile action bar, touch-friendly navigation and optimised mobile booking flow.'),
    ('Editable content', 'Five Customizer panels let the owner change contact details, homepage text, all photos, tracking IDs and custom code - without a developer.'),
    ('Page-builder ready', 'Elementor-compatible: any page can be rebuilt visually later without breaking the theme.'),
]))

st.append(Spacer(1, 4*mm))
st.append(Paragraph('2.2&nbsp;&nbsp;Booking Engine', S['h2']))
st.append(feature_table([
    ('Booking wizard', 'Guided multi-step form with live price calculation as the guest selects dates, cabins and guests.'),
    ('Six-cabin inventory', 'Five cabin types across six cabins, each with its own per-person rate and sharing capacity.'),
    ('Smart pricing', 'Automatic weekday, weekend and holiday rates with a weekday discount of up to 20%.'),
    ('Children pricing', 'Applied automatically: ages 9+ full rate, ages 4-8 half rate, ages 0-4 free.'),
    ('Live availability', 'Real-time date availability that shows remaining cabins and prevents overbooking.'),
    ('Booking management', 'Full admin screen for every booking, plus manual entry for phone and walk-in guests.'),
    ('Status workflow', 'Pending, Advance Paid, Confirmed, Completed and Cancelled - with automatic guest notification on confirmation.'),
    ('Guest self-service', 'Guests can track their own booking status using their phone number or email address.'),
    ('Discount panel', 'Percentage, flat or custom counter-offer pricing for negotiated bookings.'),
    ('Trip calendar', 'Departure schedule with per-trip booked-cabin counts and automatic "Full Booked" status.'),
    ('Guest reviews', 'Star-rated reviews managed from the dashboard and displayed on the website.'),
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
    ('BHELA Theme', 'Version 2.8.0 - design, pages, blog, SEO, analytics, custom code'),
    ('Booking Engine', 'Version 2.6.3 - bookings, pricing, invoices, trips, reviews, email and SMS'),
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

# ============ 7. ACCEPTANCE ============
st.append(Spacer(1, 8*mm))
_signhead = [
    section(7, 'Acceptance &amp; Sign-off'),
    para('By signing below, both parties confirm that the platform described in this document has '
         'been delivered and accepted, and that the commercial and support terms set out in '
         'sections 5 and 6 are agreed.'),
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
    % DOC_REF, S['small']))

doc.build(st)
print('PDF written: %s' % OUT)

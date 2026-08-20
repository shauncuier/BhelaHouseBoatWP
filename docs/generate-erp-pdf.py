# -*- coding: utf-8 -*-
"""Generate the BHELA ERP module document — Markdown AND PDF from one source.

Deliberately different from generate-delivery-pdf.py, which hardcodes its own copy
of the content while the markdown next to it claims to be the source. That left two
documents to keep in step, and they drifted. Here the CONTENT list below is the only
copy: `render_markdown()` and `render_pdf()` both read it, so the .md and the .pdf
cannot disagree.

Run:  python docs/generate-erp-pdf.py
"""

import os

from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER
from reportlab.platypus import (
    BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable, NextPageTemplate,
)

HERE = os.path.dirname(os.path.abspath(__file__))
OUT_PDF = os.path.join(HERE, "BHELA-ERP-Module.pdf")
OUT_MD = os.path.join(HERE, "BHELA-ERP-Module.md")

# Midnight Monsoon brand palette — same as the delivery document, so this reads as
# a sibling rather than a different vendor's paperwork.
INK = colors.HexColor("#0A2A2F")
INK2 = colors.HexColor("#0E3B41")
PRIMARY = colors.HexColor("#137A74")
AQUA = colors.HexColor("#6FC7BF")
CTA = colors.HexColor("#FF7A3D")
SAND = colors.HexColor("#F4EFE6")
CREAM = colors.HexColor("#FAF7F2")
TEXT = colors.HexColor("#22403E")
SOFT = colors.HexColor("#5E7472")
LINE = colors.HexColor("#D9E2E0")

DOC_REF = "3SS-BHELA-ERP-2026-08"
DOC_DATE = "18 August 2026"
PRICE = "USD 250.00"
VER = "v2.29.1"

# ---------------------------------------------------------------------------
# CONTENT — the single source for both outputs.
# Each item is (kind, payload):
#   h1       str
#   h2       str
#   p        str
#   bullets  [str, ...]
#   kv       [(label, value), ...]          two-column, label shaded
#   table    (col1_head, col2_head, [(a, b), ...])
#   callout  str
#   pagebreak None
# Inline markup is a tiny shared subset: <b>…</b> and <i>…</i>.
# ---------------------------------------------------------------------------
CONTENT = [
    ("h1", "1. What This Is"),
    ("p",
     "BHELA's booking platform sells cabins. This module is the layer underneath it that runs "
     "the <b>business</b>: what each trip cost, what the month earned, what is on the boat, who "
     "spent what, and who signed it off."),
    ("p",
     "It was built after the booking platform was delivered, on top of it, inside the same "
     "WordPress installation. It is not a separate product, a subscription, or a third-party "
     "system connected by an integration. The trip that a guest books is the same trip that gets "
     "costed, staffed, stocked and reported on."),
    ("kv", [
        ("Component", "BHELA Booking Engine " + VER + " — business management modules"),
        ("Built", "2 August 2026 to 18 August 2026"),
        ("Delivered as", "Part of the existing plugin — no separate installation"),
        ("Price", PRICE),
        ("Recurring cost", "None. No per-user fee, no subscription, no commission"),
    ]),

    ("h1", "2. Why It Was Built"),
    ("p",
     "The delivered booking platform answered one question well: <b>who has booked?</b> It could "
     "not answer the question that decides whether the business works:"),
    ("callout",
     "<b>Did that trip make money — and did this month?</b>"),
    ("p",
     "Revenue was visible because it arrived through the booking form. Cost was not. Fuel, "
     "groceries, meat, fish, gas, jetty charges, staff transport and wages were being recorded on "
     "paper and in spreadsheets, kept separately from the system that knew which trips had "
     "actually run and how many guests were on them. Nothing joined the two halves, so the "
     "month-end figure had to be assembled by hand, from memory and receipts, every month."),
    ("p",
     "Three consequences, all of which cost real money:"),
    ("bullets", [
        "<b>No per-trip profit.</b> A trip could be full and still lose money on fuel and food, and "
        "nobody would know which trips those were.",
        "<b>No stock control.</b> Plates, blankets, life jackets, engine parts and fuel were not "
        "counted against anything. Losses appeared as purchases, not as losses.",
        "<b>No separation of duties.</b> Whoever recorded a cost also approved it. In a cash "
        "business with staff handling purchases, that is the single largest control gap there is.",
    ]),
    ("p",
     "This module closes all three, inside the system that already holds the bookings."),

    ("h1", "3. What It Replaces"),
    ("table", ("Was", "Now", [
        ("Trip costs written on paper, totalled by hand",
         "A cost sheet per trip with owner-editable cost heads, three payment columns per head, "
         "and a running total the system computes"),
        ("Month-end profit assembled manually from receipts",
         "A Monthly Statement that builds itself from approved trips, less expenses by type, less "
         "payroll — with cost and profit per person"),
        ("Stock in a notebook, counted when someone remembered",
         "A permanent item register and a monthly stock sheet that opens on last month's closing "
         "automatically"),
        ("Salary recalculated by hand each month",
         "Payroll priced from the number of trips that actually ran, with advances and settlement "
         "recorded per person"),
        ("Anyone with the login could change anything",
         "Six staff roles with an owner-editable permission matrix — the store cannot touch "
         "accounts, and accounts cannot touch the store"),
        ("No record of who changed a figure",
         "An append-only audit trail: who, what it was, what it became, and when"),
    ])),

    ("pagebreak", None),
    ("h1", "4. The Modules"),

    ("h2", "4.1 Trip Costing & Approval"),
    ("table", ("Capability", "What it does", [
        ("Cost sheet per trip",
         "Every cost head the operation actually uses — fuel, electricity, groceries, meat, fish, "
         "kitchen market, gas, staff transport, jetty, water, fruit, dry fish, local bills — each "
         "with 1st, 2nd and 3rd payment columns, a remark, and spare rows for one-offs."),
        ("Owner-editable heads",
         "The list of cost heads is data, not code. Add or retire a head as the operation changes, "
         "without a developer."),
        ("Prepare, check, approve",
         "Three named steps, each stamped with who did it and when. The preparer is not the "
         "checker, and the checker is not the approver."),
        ("Locked on approval",
         "An approved sheet cannot be edited. An administrator must deliberately unlock it first, "
         "and that unlock is recorded."),
        ("Trip data pulled in",
         "Picking a trip date fills in the booking figures for that date, so the sheet is costed "
         "against the trip that actually ran."),
    ])),

    ("h2", "4.2 Expenses"),
    ("table", ("Capability", "What it does", [
        ("Non-trip spending",
         "Advertising and boosting, renovation, one-off purchases — anything not attributable to a "
         "single trip, kept separately so trip profit stays clean."),
        ("Full record per entry",
         "Date, type, amount, payment method, payment date, means of verification and remark."),
        ("Owner-editable lists",
         "Expense types and payment methods are both editable, so the categories match how the "
         "business actually spends."),
        ("Monthly view",
         "Filter by month and type, with the month's total on the filter bar."),
    ])),

    ("h2", "4.3 Monthly Statement & Yearly Report"),
    ("table", ("Capability", "What it does", [
        ("Monthly Statement",
         "The month's <b>approved</b> trips as rows, then one deduction line per expense type, "
         "then staff payroll, then gross profit. Cost per person and profit per person alongside. "
         "Print and PDF ready, with the sign-offs carried through from the cost sheets."),
        ("Approved only",
         "An unapproved cost sheet does not reach the statement. The month's figure is built from "
         "numbers somebody has checked, not from drafts."),
        ("Yearly Report",
         "Twelve monthly statements rolled up, in <b>financial-year (July to June)</b> or calendar "
         "mode, with season totals, margin, and the year's expense mix."),
        ("Cannot disagree with itself",
         "The yearly figures are produced by running the monthly statement twelve times rather "
         "than by a second calculation, so a yearly total can never contradict the month it "
         "summarises."),
    ])),

    ("h2", "4.4 Staff & Payroll"),
    ("table", ("Capability", "What it does", [
        ("Roster",
         "Each person recorded as trip-based, monthly-salaried, or both — with per-trip rate, "
         "monthly salary, designation and payment account."),
        ("Monthly salary sheet",
         "Trip-based crew priced automatically from the number of trips that actually ran that "
         "month, taken from the approved cost sheets. Advances, settlement and adjustments recorded "
         "per person."),
        ("Payroll in the bottom line",
         "Gross profit is trip profit less expenses <b>less payroll</b>. Wages are a real cost and "
         "are treated as one."),
        ("A paid month stays paid",
         "A sheet keeps the rates it was saved with. A pay rise does not rewrite last month, and "
         "hiring someone today does not add a wage bill to a month already paid."),
    ])),

    ("pagebreak", None),
    ("h2", "4.5 Inventory & Asset Register"),
    ("table", ("Capability", "What it does", [
        ("Permanent item register",
         "Per item: category, sub-category, location, unit, brand, model, serial, asset tag, "
         "barcode, supplier, purchase date and bill, warranty, unit value, responsible person and "
         "photos."),
        ("Frozen Item IDs",
         "Every item gets an ID built from its category — BHELA-KIT-0001 — that never changes and "
         "is never reused, so a label printed today still means the same thing next year."),
        ("Inventory vs asset",
         "Consumables are consumed; assets are not. The sheet offers a Used column for one and not "
         "the other, and the two report separately."),
        ("Monthly stock sheet",
         "Opens on the previous month's closing automatically. Records purchases, transfers in and "
         "out, used, lost and scrapped — and splits what is on board by condition: good, "
         "repairable, under repair, damaged."),
        ("Physical verification",
         "A counted figure per item against the system figure. A variance must be explained before "
         "the month can close. A discrepancy is reported, never quietly corrected."),
        ("Month close and lock",
         "A closed month is genuinely closed: its figures cannot be edited through the form, "
         "changed directly in the database, or deleted — not even by an administrator. Reopening is "
         "possible and is itself recorded."),
        ("CSV importer",
         "Four steps: upload, map your columns, dry run, commit. It reads the spreadsheet you "
         "already keep rather than demanding a fixed format, shows what it would do before doing "
         "it, blocks a row it cannot resolve instead of guessing, and is safe to run twice."),
        ("Two reports",
         "An Inventory Report by movement and value, and an Asset Report by location and "
         "condition — both printable and exportable to CSV."),
    ])),

    ("h2", "4.6 Access Control & Audit"),
    ("table", ("Capability", "What it does", [
        ("Six staff roles",
         "Manager, booking staff, cost preparer, cost checker and storekeeper, alongside the "
         "administrator — so office, kitchen and store each get their own login."),
        ("Editable permission matrix",
         "A Team screen with a checkbox per permission per role, saved from the page. Access is "
         "configured by the owner, not fixed in code."),
        ("Role-aware admin",
         "Each person sees only the menus they can use. A storekeeper sees the store and nothing "
         "else."),
        ("Append-only audit trail",
         "Who changed which figure, what it was before, what it became, and when. It has no delete "
         "path and no clear button, by design — it is the register's memory, not a log to tidy up."),
    ])),

    ("h2", "4.7 Operational Reporting"),
    ("table", ("Capability", "What it does", [
        ("Trip Report",
         "Every booking for a date or a range with advance, due and totals — printable, "
         "exportable to CSV, and shareable as a WhatsApp message so the crew know who is aboard."),
        ("One admin, four menus",
         "The business screens are grouped by the job being done — Bookings, Accounts, Store, "
         "Setup — each hidden from anyone who cannot use it, so a storekeeper is not shown the "
         "accounts."),
        ("Consistent design",
         "One design system across every screen: headers, summary cards, ledger tables, status "
         "pills and print styles."),
    ])),

    ("h1", "5. Internal Controls"),
    ("p",
     "This is the part that matters most in a cash business, and the reason the module is more "
     "than a set of forms. Four controls are built in rather than relied upon:"),
    ("bullets", [
        "<b>Separation of duties.</b> Preparing a cost sheet, checking it and approving it are three "
        "different permissions. They can be given to three different people.",
        "<b>Named sign-offs.</b> Every approval records who and when, and that record appears on the "
        "printed statement.",
        "<b>Immutability after sign-off.</b> An approved cost sheet and a closed stock month cannot be "
        "quietly edited. Changing one requires a deliberate, recorded reopening.",
        "<b>An audit trail that cannot be cleaned.</b> There is no delete and no clear button. If a "
        "figure moved, the record of it moving still exists.",
    ]),
    ("callout",
     "Together these mean a figure in the monthly statement can be traced to the person who "
     "entered it, the person who checked it, and the person who approved it — and none of them "
     "could have altered it afterwards without leaving a mark."),

    ("h1", "6. Quality Assurance"),
    ("p",
     "These modules handle money, so they are tested automatically rather than by eye."),
    ("table", ("", "What it does", [
        ("14 test harnesses",
         "Run from a single command, covering pricing, a real month's statement reproduced to the "
         "taka, salary, cost sheets, the booking save path, the stock register, every admin screen, "
         "colour contrast, front-end behaviour behind a page cache, mobile verification, the SMS "
         "gateway, version consistency and the yearly rollup."),
        ("Run before every release",
         "Every future change is checked against all of it. The suite has already caught real "
         "defects — including several in these modules — before they reached the live site."),
        ("Real-data verification",
         "The modules were additionally operated end to end with real entries rather than test "
         "fixtures, which is how several defects invisible to the tests were found and fixed."),
    ])),

    ("pagebreak", None),
    ("h1", "7. What It Does Not Do"),
    ("p",
     "Stated plainly, so expectations are set correctly. This is operational management "
     "reporting for a houseboat business — not a certified accounting package."),
    ("bullets", [
        "It is not double-entry bookkeeping and produces no trial balance, ledger or balance sheet.",
        "It does not calculate, file or report VAT, income tax or payroll tax.",
        "It does not reconcile against bank or bKash statements automatically — payment references "
        "are recorded by hand.",
        "It is single-currency (BDT) and single-boat.",
        "It does not replace an accountant. It gives an accountant clean, signed-off monthly figures "
        "to work from.",
    ]),

    ("h1", "8. Value & Commercial Terms"),
    ("p",
     "The modules in section 4 were built after the booking platform was delivered and are outside "
     "the scope of the original project. They are charged once, at " + PRICE + ", with no recurring "
     "fee of any kind."),
    ("table", ("Module", "Indicative value if quoted separately", [
        ("Trip costing, approval workflow and expenses", "USD 110.00"),
        ("Monthly Statement and Yearly Report", "USD 70.00"),
        ("Staff roster and payroll", "USD 55.00"),
        ("Inventory &amp; Asset Register, importer and both reports", "USD 160.00"),
        ("Staff roles, permission matrix and audit trail", "USD 60.00"),
        ("Trip Report, admin design system and menu restructure", "USD 55.00"),
        ("Automated regression suite (14 harnesses)", "USD 70.00"),
        ("<b>Total indicative value</b>", "<b>USD 580.00</b>"),
    ])),
    ("p",
     "<i>The figures above are indicative comparisons for a single custom build of each module, "
     "given so the scope can be judged. They are not a quotation for separate work.</i>"),
    ("kv", [
        ("Total indicative value", "USD 580.00"),
        ("Amount charged", PRICE),
        ("Client receives free", "USD 330.00"),
    ]),
    ("p",
     "Also delivered in the same period and <b>included at no additional charge</b>: guest reviews "
     "with photo upload and moderation, mobile number verification on the booking form with SMS "
     "credit monitoring, the Booking Guide page with two-level navigation, and editable "
     "sightseeing spots. None of these are ERP modules and none are charged for."),

    ("h2", "8.1 Why this is inexpensive for what it is"),
    ("bullets", [
        "<b>No subscription.</b> Comparable inventory or ERP software is normally a monthly fee per "
        "user, for as long as the business runs. This is bought once.",
        "<b>No integration cost.</b> It is inside the booking system, so the trip that was booked is "
        "the trip that gets costed. There is no export, no import, and no double entry.",
        "<b>Owned outright.</b> The source code belongs to the client, along with the data. There is "
        "no licence, no lock-in and no per-booking commission.",
        "<b>Built for this operation.</b> Cost heads, expense types, stock categories, locations and "
        "staff roles are all the owner's own lists, not a generic chart of accounts to work around.",
    ]),
    ("callout",
     "<b>" + PRICE + " once, for the module described in section 4.</b> No subscription, no per-user "
     "fee, no commission, full source ownership."),
]

SIGN = [
    ("3s-Soft", "Jashedul Islam Shaun", "Founder, 3s-Soft"),
    ("KeyToBD", "Kaisar Hamid Apon", "Owner, KeyToBD"),
]


# ---------------------------------------------------------------------------
# Markdown renderer
# ---------------------------------------------------------------------------
def md_inline(t):
    return t.replace("&amp;", "&").replace("&#8212;", "—")


def render_markdown():
    L = []
    L.append("# BHELA — The Haor Exclusive")
    L.append("## ERP Module — Business Management, Accounting & Inventory")
    L.append("")
    L.append("> Generated by `docs/generate-erp-pdf.py`, which also produces the PDF. "
             "Edit the CONTENT list in that script — it is the only copy.")
    L.append("> Document ref: **%s** · %s" % (DOC_REF, DOC_DATE))
    L.append("")
    L.append("| | |")
    L.append("|---|---|")
    L.append("| **Project** | BHELA – The Haor Exclusive |")
    L.append("| **Module** | ERP — trip costing, accounting, inventory, payroll, access control |")
    L.append("| **Developer** | 3s-Soft — Jashedul Islam Shaun (3s-soft.com) |")
    L.append("| **Client** | KeyToBD — Kaisar Hamid Apon |")
    L.append("| **Price** | **%s** (one-off, no recurring fee) |" % PRICE)
    L.append("")
    L.append("---")
    L.append("")

    for kind, payload in CONTENT:
        if kind == "h1":
            L += ["## " + md_inline(payload), ""]
        elif kind == "h2":
            L += ["### " + md_inline(payload), ""]
        elif kind == "p":
            L += [md_inline(payload), ""]
        elif kind == "bullets":
            L += ["- " + md_inline(b) for b in payload] + [""]
        elif kind == "callout":
            L += ["> " + md_inline(payload), ""]
        elif kind == "kv":
            L += ["| | |", "|---|---|"]
            L += ["| **%s** | %s |" % (md_inline(a), md_inline(b)) for a, b in payload]
            L += [""]
        elif kind == "table":
            h1, h2, rows = payload
            L += ["| %s | %s |" % (h1 or " ", h2), "|---|---|"]
            L += ["| %s | %s |" % (md_inline(a), md_inline(b)) for a, b in rows]
            L += [""]
        elif kind == "pagebreak":
            pass

    L += ["---", "", "## 9. Acceptance & Sign-off", ""]
    L += ["Both parties confirm the ERP module described in section 4 has been delivered and "
          "accepted, and that the commercial terms in section 8 are agreed.", ""]
    L += ["| Developer | Client |", "|---|---|"]
    L += ["| **%s** | **%s** |" % (SIGN[0][0], SIGN[1][0])]
    L += ["| %s — %s | %s — %s |" % (SIGN[0][1], SIGN[0][2], SIGN[1][1], SIGN[1][2])]
    L += ["| Signature: ______________________ | Signature: ______________________ |"]
    L += ["| Date: ______________________ | Date: ______________________ |", ""]
    L += ["---", ""]
    L += ["*BHELA – The Haor Exclusive · ERP module document · %s (%s)*" % (DOC_REF, DOC_DATE)]
    L += ["*Designed and developed by **3s-Soft** — 3s-soft.com · © 2026 3s-Soft. "
          "All rights reserved.*"]

    with open(OUT_MD, "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(L))
    print("MD  written: %s" % OUT_MD)


# ---------------------------------------------------------------------------
# PDF renderer
# ---------------------------------------------------------------------------
styles = getSampleStyleSheet()
S = {}
S["title"] = ParagraphStyle("title", parent=styles["Title"], fontName="Helvetica-Bold",
                            fontSize=25, leading=30, textColor=colors.white, alignment=TA_CENTER)
S["subtitle"] = ParagraphStyle("subtitle", parent=styles["Normal"], fontName="Helvetica",
                               fontSize=12, leading=17, textColor=AQUA, alignment=TA_CENTER)
S["covermeta"] = ParagraphStyle("covermeta", parent=styles["Normal"], fontName="Helvetica",
                                fontSize=9.5, leading=14, textColor=colors.white,
                                alignment=TA_CENTER)
S["h1"] = ParagraphStyle("h1", parent=styles["Heading1"], fontName="Helvetica-Bold",
                         fontSize=16, leading=20, textColor=INK, spaceBefore=6, spaceAfter=8)
S["h2"] = ParagraphStyle("h2", parent=styles["Heading2"], fontName="Helvetica-Bold",
                         fontSize=11.5, leading=15, textColor=PRIMARY, spaceBefore=10, spaceAfter=4)
S["body"] = ParagraphStyle("body", parent=styles["Normal"], fontName="Helvetica",
                           fontSize=9.8, leading=14.5, textColor=TEXT, spaceAfter=5)
S["bullet"] = ParagraphStyle("bullet", parent=S["body"], leftIndent=11, bulletIndent=2,
                             spaceAfter=3.5)
S["cell"] = ParagraphStyle("cell", parent=styles["Normal"], fontName="Helvetica",
                           fontSize=9.1, leading=13, textColor=TEXT)
S["cellb"] = ParagraphStyle("cellb", parent=S["cell"], fontName="Helvetica-Bold")
S["cellw"] = ParagraphStyle("cellw", parent=S["cell"], textColor=colors.white,
                            fontName="Helvetica-Bold")
S["small"] = ParagraphStyle("small", parent=styles["Normal"], fontName="Helvetica",
                            fontSize=8, leading=11, textColor=SOFT)


def para(t, s="body"):
    return Paragraph(t, S[s])


def bullets(items):
    return [Paragraph(t, S["bullet"], bulletText="•") for t in items]


def feature_table(head1, head2, rows, w1=54 * mm):
    data = [[Paragraph("<b>%s</b>" % (head1 or " "), S["cellw"]),
             Paragraph("<b>%s</b>" % head2, S["cellw"])]]
    for a, b in rows:
        data.append([Paragraph(a, S["cellb"]), Paragraph(b, S["cell"])])
    t = Table(data, colWidths=[w1, 168 * mm - w1], repeatRows=1)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), INK),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("LINEBELOW", (0, 1), (-1, -1), 0.4, LINE),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, CREAM]),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
    ]))
    return t


def info_table(rows):
    data = [[Paragraph(a, S["cellb"]), Paragraph(b, S["cell"])] for a, b in rows]
    t = Table(data, colWidths=[52 * mm, 116 * mm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 4.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4.5),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, LINE),
        ("BACKGROUND", (0, 0), (0, -1), SAND),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
    ]))
    return t


def callout(text, bg=SAND, bar=CTA):
    inner = Table([[Paragraph(text, S["cell"])]], colWidths=[164 * mm])
    inner.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), bg),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("LINEBEFORE", (0, 0), (0, -1), 3, bar),
    ]))
    return inner


def cover_page(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(INK)
    canvas.rect(0, 0, w, h, stroke=0, fill=1)
    canvas.setFillColor(INK2)
    canvas.rect(0, h - 118 * mm, w, 118 * mm, stroke=0, fill=1)
    canvas.setFillColor(CTA)
    canvas.rect(0, h - 121 * mm, w, 2.2 * mm, stroke=0, fill=1)
    canvas.setFillColor(AQUA)
    canvas.setFont("Helvetica-Bold", 9)
    canvas.drawString(25 * mm, 20 * mm, "Document ref: %s" % DOC_REF)
    canvas.setFillColor(colors.white)
    canvas.setFont("Helvetica", 8.5)
    canvas.drawRightString(w - 25 * mm, 20 * mm, "3s-Soft  |  3s-soft.com")
    canvas.restoreState()


def body_page(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(INK)
    canvas.rect(0, h - 13 * mm, w, 13 * mm, stroke=0, fill=1)
    canvas.setFillColor(colors.white)
    canvas.setFont("Helvetica-Bold", 8.5)
    canvas.drawString(21 * mm, h - 8.8 * mm, "BHELA  |  ERP Module")
    canvas.setFillColor(AQUA)
    canvas.setFont("Helvetica", 8)
    canvas.drawRightString(w - 21 * mm, h - 8.8 * mm, DOC_DATE)
    canvas.setFillColor(LINE)
    canvas.rect(21 * mm, 14 * mm, w - 42 * mm, 0.3, stroke=0, fill=1)
    canvas.setFillColor(SOFT)
    canvas.setFont("Helvetica", 7.8)
    canvas.drawString(21 * mm, 10.5 * mm, "3s-Soft  |  %s" % DOC_REF)
    canvas.drawRightString(w - 21 * mm, 10.5 * mm, "Page %d" % doc.page)
    canvas.restoreState()


def render_pdf():
    doc = BaseDocTemplate(OUT_PDF, pagesize=A4,
                          leftMargin=21 * mm, rightMargin=21 * mm,
                          topMargin=20 * mm, bottomMargin=18 * mm,
                          title="BHELA ERP Module", author="3s-Soft")
    frame_cover = Frame(25 * mm, 30 * mm, A4[0] - 50 * mm, A4[1] - 60 * mm, id="cover")
    frame_body = Frame(21 * mm, 18 * mm, A4[0] - 42 * mm, A4[1] - 38 * mm, id="body")
    doc.addPageTemplates([
        PageTemplate(id="cover", frames=[frame_cover], onPage=cover_page),
        PageTemplate(id="body", frames=[frame_body], onPage=body_page),
    ])

    st = []
    st.append(Spacer(1, 32 * mm))
    st.append(Paragraph("BHELA &#8212; The Haor Exclusive", S["subtitle"]))
    st.append(Spacer(1, 4 * mm))
    st.append(Paragraph("ERP Module", S["title"]))
    st.append(Spacer(1, 3 * mm))
    st.append(Paragraph("Business Management, Accounting &amp; Inventory", S["subtitle"]))
    st.append(Spacer(1, 16 * mm))
    st.append(Paragraph(
        "Trip costing and approval &nbsp;&#183;&nbsp; Expenses &nbsp;&#183;&nbsp; Monthly and "
        "yearly statements<br/>Payroll &nbsp;&#183;&nbsp; Inventory and asset register "
        "&nbsp;&#183;&nbsp; Access control and audit trail", S["covermeta"]))
    st.append(Spacer(1, 18 * mm))
    st.append(Paragraph(
        "Prepared by <b>3s-Soft</b> for <b>KeyToBD</b><br/>%s" % DOC_DATE, S["covermeta"]))
    st.append(Spacer(1, 10 * mm))
    st.append(Paragraph(
        '<font color="#FF7A3D" size=17><b>%s</b></font><br/>'
        '<font size=8.5>one-off &#183; no subscription &#183; no commission</font>' % PRICE,
        S["covermeta"]))

    st.append(NextPageTemplate("body"))
    st.append(PageBreak())

    st.append(info_table([
        ("Project", "BHELA – The Haor Exclusive"),
        ("Module", "ERP — trip costing, accounting, inventory, payroll, access control"),
        ("Developer", "3s-Soft — Jashedul Islam Shaun, Founder"),
        ("Client", "KeyToBD — Kaisar Hamid Apon, Owner"),
        ("Document ref", DOC_REF),
        ("Date", DOC_DATE),
        ("Price", "<b>%s</b> (one-off, no recurring fee)" % PRICE),
    ]))
    st.append(Spacer(1, 7 * mm))

    for kind, payload in CONTENT:
        if kind == "h1":
            st.append(Paragraph(payload.replace("&", "&amp;"), S["h1"]))
        elif kind == "h2":
            st.append(Paragraph(payload.replace("&", "&amp;"), S["h2"]))
        elif kind == "p":
            st.append(para(payload))
        elif kind == "bullets":
            st.extend(bullets(payload))
            st.append(Spacer(1, 2 * mm))
        elif kind == "callout":
            st.append(Spacer(1, 2 * mm))
            st.append(callout(payload))
            st.append(Spacer(1, 3 * mm))
        elif kind == "kv":
            st.append(info_table(payload))
            st.append(Spacer(1, 3 * mm))
        elif kind == "table":
            h1, h2, rows = payload
            st.append(feature_table(h1, h2, rows))
            st.append(Spacer(1, 3 * mm))
        elif kind == "pagebreak":
            st.append(PageBreak())

    # Sign-off
    st.append(Spacer(1, 8 * mm))
    head = [
        Paragraph("9.&nbsp;&nbsp;Acceptance &amp; Sign-off", S["h1"]),
        para("By signing below, both parties confirm that the ERP module described in section 4 "
             "has been delivered and accepted, and that the commercial terms in section 8 — "
             "<b>%s</b>, one-off — are agreed." % PRICE),
        Spacer(1, 10 * mm),
    ]
    sign = Table([
        [Paragraph("<b>Developer</b>", S["cellb"]), "", Paragraph("<b>Client</b>", S["cellb"])],
        [Paragraph(SIGN[0][0], S["cell"]), "", Paragraph(SIGN[1][0], S["cell"])],
        [Spacer(1, 14 * mm), "", Spacer(1, 14 * mm)],
        [Paragraph('%s<br/><font size=8 color="#5E7472">%s</font>' % (SIGN[0][1], SIGN[0][2]),
                   S["cell"]), "",
         Paragraph('%s<br/><font size=8 color="#5E7472">%s</font>' % (SIGN[1][1], SIGN[1][2]),
                   S["cell"])],
        [Paragraph("Date: ______________________", S["small"]), "",
         Paragraph("Date: ______________________", S["small"])],
    ], colWidths=[75 * mm, 18 * mm, 75 * mm])
    sign.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "BOTTOM"),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
        ("LINEBELOW", (0, 2), (0, 2), 0.8, INK),
        ("LINEBELOW", (2, 2), (2, 2), 0.8, INK),
        ("BOTTOMPADDING", (0, 3), (-1, 3), 8),
    ]))
    st.append(KeepTogether(head + [sign]))

    st.append(Spacer(1, 12 * mm))
    st.append(HRFlowable(width="100%", thickness=0.6, color=LINE))
    st.append(Spacer(1, 3 * mm))
    st.append(Paragraph(
        "BHELA - The Haor Exclusive &nbsp;|&nbsp; ERP module document &nbsp;|&nbsp; %s (%s)<br/>"
        "Designed and developed by <b>3s-Soft</b> - 3s-soft.com &nbsp;|&nbsp; &#169; 2026 "
        "3s-Soft. All rights reserved." % (DOC_REF, DOC_DATE), S["small"]))

    doc.build(st)
    print("PDF written: %s" % OUT_PDF)


if __name__ == "__main__":
    render_markdown()
    render_pdf()

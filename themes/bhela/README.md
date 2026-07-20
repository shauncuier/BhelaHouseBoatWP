# 🎨 BHELA WordPress Theme (v2.11.0)

This is the custom theme for **BHELA – The Haor Exclusive** houseboat experience. It is designed to render a premium, immersive, and fast experience tailored for travelers exploring Tanguar Haor, Sunamganj.

---

## 🌌 Visual Design & Aesthetic: "Midnight Monsoon"

The theme utilizes a custom-curated, dark-teal aesthetic named **Midnight Monsoon**.
*   **Color Palette**: Deep dark-teals (representing the night waters of the Haor), accented with rich mustard-gold and warm sand-beige tones.
*   **Typography**: Clean sans-serif Hind Siliguri for readable Bengali copy, paired with the serif Fraunces for sophisticated English headings.
*   **Micro-animations**: Smooth hover transitions, modal fades, and interactive accordion animations to elevate the user experience.

---

## 📄 Custom Page Templates

The theme includes several pre-built template files under `page-templates/`:

| File Name | Template Name | Bengali Title | Purpose |
|---|---|---|---|
| `template-cabins.php` | কেবিন ও রেট | কেবিন ও রেট | Lists cabin details, sharing capacities, and pricing cards dynamically. |
| `template-schedule.php` | ট্রিপ সিডিউল | ট্রিপ সিডিউল | Renders the schedule table and departure dates. |
| `template-food.php` | খাবার মেনু | খাবার মেনু | Displays day-by-day menu choices (Breakfast, Lunch, Dinner, Snacks). |
| `template-gallery.php` | গ্যালারি | গ্যালারি | Automatically loops and scans images in asset directories. |
| `template-faq.php` | সাধারণ প্রশ্ন (FAQ) | সাধারণ প্রশ্ন (FAQ) | Clean accordion list of common customer inquiries. |
| `template-booking.php` | বুক করুন | বুক করুন | Main interface hosting the BHELA Booking Engine frontend form. |
| `template-policy.php` | বুকিং নীতিমালা | বুকিং নীতিমালা | Policy list regarding payment options, advance refunds, and cancellations. |

*Note: On theme activation, if these pages do not exist, they are automatically generated and set to their corresponding templates.*

---

## ⚙️ Customizer Controls

The theme registers custom settings in the WordPress Customizer under **BHELA Contact**:
*   `bhela_phone_1` / `bhela_phone_2` — Phone numbers displayed in the header and footer.
*   `bhela_whatsapp` — Mobile number used for WhatsApp deep-links (e.g. `wa.me` links).
*   `bhela_email` — Contact email.
*   `bhela_facebook` — Social media links.
*   `bhela_address` — Location address displayed on-page and in footer metadata.

*Integrations*: These fields will fall back automatically to the custom settings defined in the BHELA Booking Engine plugin if not explicitly set in the Customizer.

---

## 🔌 Booking Engine Integration & Localized Scripts

The theme communicates seamlessly with the booking plugin:
*   **Live Pricing Estimator**: On the homepage hero section, a rate estimator runs inside client-side JS (`assets/js/theme.js`).
*   **Rate Injection**: `wp_localize_script()` is utilized inside `functions.php` to fetch live cabin pricing, holiday dates, and weekend settings from the database and inject them directly into client-side JS.
*   This allows the client-side calculator to instantly compute weekday vs weekend pricing without making repeated server API calls.

---

## 🖼️ Auto-Gallery Asset Loading

The **Gallery Page** template reads images directly from the following directories inside `assets/images/`:
*   `assets/images/hero/` — Hero banners.
*   `assets/images/cabins/` — Images of the cabin classes.
*   `assets/images/spots/` — Tanguar Haor sightseeing spots.
*   `assets/images/food/` — Onboard food menus.
*   `assets/images/boat/` — Boat exteriors, decks, and common spaces.

Simply drop images in these folders and the gallery will populate them automatically.

---

## 🔍 SEO & Schema Markup

To ensure maximum SEO ranking, the theme injects a JSON-LD schema into the `<head>` of the homepage:
*   `TouristAttraction` schema mapping the location as Anwarpur Ghat, Tahirpur, Sunamganj.
*   Includes contact information, homepage URLs, and language tags.

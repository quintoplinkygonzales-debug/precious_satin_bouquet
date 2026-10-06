# Precious Satin Bouquet — Presentation Pitch Script
### Target Award: 👑 Best Web System (+20 PIT)

---

## 🎤 OPENING (30 seconds)
> Deliver with confidence. Set the scene first.

"Good [morning/afternoon]. We are Group [X], and we built **Precious Satin Bouquet** —
a fully functional flower bouquet e-commerce system built from scratch using
PHP, MySQL, HTML, CSS, and JavaScript. No frameworks. No shortcuts.
Every feature you're about to see was handcrafted by our team."

---

## 🌸 WHAT IS PRECIOUS SATIN BOUQUET? (30 seconds)

"Precious Satin Bouquet is an online bouquet shop that lets customers browse, customize,
and order flower arrangements — with a complete admin dashboard to manage
everything on the backend. It works like a real business system, not just a demo."

---

## 💻 LIVE DEMO FLOW (2–3 minutes)
> Walk through the site as you speak. Keep it moving.

**1. Homepage**
"When a customer visits, they're greeted with a loading screen animation,
falling petals, and a welcome popup that changes depending on whether they're
a new or returning user. The hero section, category navigation, and product
cards all load dynamically from the database."

**2. Category Filtering**
"Customers can filter by category — Crochet, Fresh, Fuzzy Wire, Plastic, Satin,
Readymade — using AJAX. The page doesn't reload. It fetches data from the server
in the background and updates the grid instantly. The URL also updates, so the
browser back button still works."

**3. Product Customization**
"For customizable bouquets, customers can choose a wrapper, ribbon color, and
add-ons like chocolates or a letter. The price updates in real time before they
add to cart."

**4. Checkout & Payment**
"Checkout supports two delivery modes — delivery or pickup — and two payment
methods: Cash on Delivery, or GCash. For GCash, the customer uploads a
screenshot of their payment. The system stores the reference number and
screenshot path in the database."

**5. Order Tracking**
"Customers can track their orders through statuses: Pending, Preparing,
Out for Delivery, Delivered, or Picked Up. They can also leave a star rating
and review after receiving their order."

**6. Admin Dashboard**
"Admins log in through a separate portal. The dashboard shows total orders,
total sales, products sold, and customer count — all pulled live from the
database. There's a GCash verification panel where admins review and approve
payments, an orders management page, and a contact messaging system with
reply functionality."

---

## 🏗️ SYSTEM ARCHITECTURE (1 minute)

"Our database has **11 tables** with proper relational structure:
- Foreign keys with CASCADE rules
- Indexes on frequently queried columns like status, user_id, and created_at
- CHECK constraints to enforce data integrity — for example, quantity must be
  greater than zero, and ratings must be between 1 and 5
- InnoDB engine with UTF-8 character encoding

Our PHP follows industry standards — prepared statements throughout to prevent
SQL injection, bcrypt password hashing, session-based authentication with
role checking on every admin page."

---

## 🎨 UI/UX HIGHLIGHTS (30 seconds)

"On the design side: we have a consistent pink color palette using CSS variables,
Google Fonts with Cormorant Garamond and Raleway, shimmer skeleton loading states
while images load, scroll-reveal animations on product cards, ripple click effects
on category buttons, and a fully responsive layout that works on mobile and desktop."

---

## 🧹 CODE QUALITY (30 seconds)

"We also focused on clean code. Our query logic is extracted into a reusable
`getProductsByCategory()` function with proper PHP type declarations and a
docblock comment — following the DRY principle, Don't Repeat Yourself.
All user-facing output is sanitized using `htmlspecialchars()`.
Our code is organized, commented, and readable."

---

## 🔑 KEY FEATURES SUMMARY

| Feature | Detail |
|---|---|
| User Authentication | Session-based login, bcrypt hashing, role separation |
| Product Catalog | 36 products across 6 categories, dynamic loading |
| Bouquet Customization | Wrapper, ribbon, add-ons with real-time pricing |
| Cart System | Persistent cart with quantity checks |
| Checkout | Cash or GCash payment, delivery or pickup |
| GCash Verification | Screenshot upload + admin approval panel |
| Order Tracking | 6 statuses, customer confirmation |
| Reviews | Star rating system, one review per order |
| Admin Dashboard | Sales stats, order management, messaging |
| Contact System | Customer messages with admin replies |
| Geolocation | User location stored in profile |
| UI Animations | Loader, petals, shimmer, reveal, ripple effects |
| Database | 11 tables, FK constraints, indexes, CHECK rules |
| Clean Code | Reusable functions, prepared statements, comments |

---

## 🏆 CLOSING (20 seconds)

"Precious Satin Bouquet is more than a school project. It's a working e-commerce system
that covers the full customer journey — from browsing to ordering to receiving
and reviewing — with a professional admin backend behind it. We believe it
demonstrates coding, design, utility, and execution at the level required for
**Best Web System**. Thank you."

---

## 💡 TIPS FOR DELIVERY

- **Assign roles**: One person demos the customer side, one demos the admin side
- **Practice the demo**: Know exactly which buttons to click and in what order
- **Dress sharp**: Remember the Best Dressed awards — corporate attire
- **Speak clearly on the architecture slide**: Judges notice when students actually understand their own database
- **Mention the fix**: "We refactored our query logic into a reusable function following DRY principles" — this directly targets the Clean Code Award

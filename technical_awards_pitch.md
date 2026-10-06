# Technical Major Awards — Pitch Additions
### (+15 Points to Laboratory Grade each)

---

## 🏗️ Best System Architecture

"Our database was designed with real-world production standards in mind.

We have 11 tables using InnoDB with UTF-8 encoding. Every relationship
is enforced through foreign keys with CASCADE rules — so when an order
is deleted, its items are automatically removed too. No orphaned data.

We indexed the most-queried columns: status, user_id, created_at, and
updated_at — so our admin order filters and dashboards stay fast even
as data grows.

Data integrity is enforced at the database level, not just in PHP.
We have CHECK constraints on quantity (must be greater than zero),
stock (cannot go negative), and ratings (must be between 1 and 5).
A unique constraint prevents a user from reviewing the same order twice.

The data flow is logical and complete: a customer builds a cart,
proceeds to checkout, the order is recorded with all items and
customizations, payment is processed and verified, delivery status
is tracked through 6 stages, and finally the customer confirms
receipt and can leave a review.

On the PHP side, every admin page verifies both session login AND
role before executing — double-layered security baked into
the architecture itself."

---

## 🧹 Clean Code Award

"We approached code quality as seriously as functionality.

First, we applied the DRY principle — Don't Repeat Yourself. Our
product query logic previously appeared three times in index.php.
We refactored it into a single reusable function,
getProductsByCategory(), with proper PHP type declarations on the
parameters and return type, and a docblock comment explaining what
it does, what it accepts, and what it returns. This is standard
industry practice.

Second, all user-facing output is sanitized using htmlspecialchars()
— preventing cross-site scripting vulnerabilities.

Third, we use prepared statements with bind_param() for every query
that involves user input — preventing SQL injection at the code level.

Fourth, our files are well-organized: config.php handles the database
connection, admin pages are separate from customer pages, the navbar
is its own include file, and each page has a clear single responsibility.

Fifth, our CSS uses custom properties (CSS variables) for the entire
color palette — so if the brand color changes, we update one line, not
fifty. That's clean, maintainable code.

Finally, we use consistent naming: camelCase for PHP variables,
kebab-case for CSS classes, and visual section separators in comments
to make the code easy to navigate."

---

## 🔌 Best API Integration

"Precious Satin Bouquet integrates several external APIs and browser services
to enhance functionality.

The most significant is the Browser Geolocation API. When a customer
registers or updates their profile, we request their precise GPS
coordinates using navigator.geolocation. These coordinates —
latitude and longitude — are stored directly in our users table.
This enables location-aware features for delivery distance
calculations.

We also integrate Google Fonts API, loading Cormorant Garamond and
Raleway as external typographic resources — ensuring consistent,
professional rendering across all devices without bundling fonts
locally.

Our internal AJAX API architecture also deserves mention. We built
our own RESTful-style endpoint — index.php?ajax=1 — that returns
structured JSON responses for the category filtering system. The
frontend sends a fetch() request, receives a JSON object containing
a title and HTML payload, and updates the DOM without a page reload.
This is the same pattern used by professional e-commerce platforms."

---

## 💡 Delivery Tips for Technical Awards

- For Architecture: Open phpMyAdmin or your SQL file during the demo
  and show the table structure with indexes and constraints visible.
  Judges are impressed when students can point to actual database design.

- For Clean Code: Pull up the getProductsByCategory() function on screen.
  Show the docblock, the type declarations, and explain what DRY means.
  Judges love students who can explain the WHY behind their code choices.

- For API Integration: Show the browser asking for location permission
  during registration. Then show the latitude/longitude saved in the
  users table. That's a live API call happening in real time.

- All three: Speak with confidence. The judges are evaluating whether
  you understand your own system — not just whether it runs.

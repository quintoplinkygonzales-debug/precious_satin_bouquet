<?php
// ─────────────────────────────────────────────────────────────────────
// PATCH for orders.php
// In your orders.php, find the .ob-total-row div inside ob-detail-inner
// and REPLACE it with the block below.
// This adds a "Track Order" button linking to order_status.php
// ─────────────────────────────────────────────────────────────────────
?>

              <div class="ob-total-row">
                <span class="ob-total-label">Order total</span>
                <div style="display:flex;align-items:center;gap:12px;">
                  <a href="order_status.php?order_id=<?= $row['id'] ?>"
                     onclick="event.stopPropagation()"
                     style="font-size:0.8rem;font-family:'DM Sans',sans-serif;font-weight:500;
                            background:var(--bark,#2c1f14);color:#faf8f5;border-radius:30px;
                            padding:6px 16px;text-decoration:none;white-space:nowrap;
                            transition:background 0.15s;">
                    📦 Track Order
                  </a>
                  <span class="ob-total-amount">₱<?= number_format($row['total_price'], 2) ?></span>
                </div>
              </div>
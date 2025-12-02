# Safe/Unsafe Counts Feature

## Overview
Added "Safe" and "Unsafe" clickable counts to the Risk Assessment section on the admin page, allowing users to filter images by risk level AND user decision.

## Changes Made

### 1. Database Method Updated
**File:** `includes/class-wp-image-guardian-database.php`

**Method:** `get_risk_breakdown()`

**Added SQL aggregations:**
```sql
SUM(CASE WHEN user_decision = 'safe' THEN 1 ELSE 0 END) as safe,
SUM(CASE WHEN user_decision = 'unsafe' THEN 1 ELSE 0 END) as unsafe
```

**Returns:**
```php
[
    'high' => ['total' => 0, 'reviewed' => 0, 'safe' => 0, 'unsafe' => 0],
    'medium' => ['total' => 0, 'reviewed' => 0, 'safe' => 0, 'unsafe' => 0],
    'low' => ['total' => 4, 'reviewed' => 4, 'safe' => 4, 'unsafe' => 0],
]
```

---

### 2. Template Updated
**File:** `templates/admin-page.php`

**Added two new rows per risk level:**
- **Safe:** Links to `upload.php?risk_level=low&user_decision=safe`
- **Unsafe:** Links to `upload.php?risk_level=low&user_decision=unsafe`

**Before:**
```
High Risk
Total: 0
Reviewed: 0
```

**After:**
```
High Risk
Total: 0
Reviewed: 0
Safe: 0
Unsafe: 0
```

---

### 3. Media Library Filtering
**File:** `includes/class-wp-image-guardian-media.php`

**Method:** `filter_media_library_query()`

**Added parameter handling:**
- Accepts `user_decision` GET parameter
- Values: `safe` or `unsafe`
- Filters: `ig.user_decision = 'safe'` or `ig.user_decision = 'unsafe'`

**Works in combination with:**
- `risk_level` - High, Medium, Low
- `reviewed` - Yes/No
- `checked` - Yes/No

---

### 4. CSS Styling
**File:** `assets/css/admin.css`

**Renamed classes for consistency:**
- `.risk-total` and `.risk-reviewed` → `.risk-item`
- All 4 items (Total, Reviewed, Safe, Unsafe) now share the same styling
- Color-coded by risk level (red for high, yellow for medium, green for low)

---

## Link Destinations

### High Risk
- **Total:** `upload.php?risk_level=high` (all high-risk images)
- **Reviewed:** `upload.php?risk_level=high&reviewed=yes` (reviewed high-risk)
- **Safe:** `upload.php?risk_level=high&user_decision=safe` (marked safe)
- **Unsafe:** `upload.php?risk_level=high&user_decision=unsafe` (marked unsafe)

### Medium Risk
- **Total:** `upload.php?risk_level=medium`
- **Reviewed:** `upload.php?risk_level=medium&reviewed=yes`
- **Safe:** `upload.php?risk_level=medium&user_decision=safe`
- **Unsafe:** `upload.php?risk_level=medium&user_decision=unsafe`

### Low Risk
- **Total:** `upload.php?risk_level=low`
- **Reviewed:** `upload.php?risk_level=low&reviewed=yes`
- **Safe:** `upload.php?risk_level=low&user_decision=safe`
- **Unsafe:** `upload.php?risk_level=low&user_decision=unsafe`

---

## Visual Result

```
┌──────────────────────────────────────────────┐
│           Risk Assessment                    │
├──────────────────────────────────────────────┤
│                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │High Risk │  │Medium    │  │Low Risk  │  │
│  │          │  │Risk      │  │          │  │
│  │Total: 0  │  │Total: 0  │  │Total: 4  │  │
│  │Reviewed:0│  │Reviewed:0│  │Reviewed:4│  │
│  │Safe: 0   │  │Safe: 0   │  │Safe: 4   │  │ ← NEW
│  │Unsafe: 0 │  │Unsafe: 0 │  │Unsafe: 0 │  │ ← NEW
│  └──────────┘  └──────────┘  └──────────┘  │
└──────────────────────────────────────────────┘
```

All numbers are clickable links!

---

## Use Cases

### 1. View All Safe Low-Risk Images
Click: **Low Risk → Safe: 4**
→ Shows images that were checked, found to be low risk, and marked as safe

### 2. View Unsafe High-Risk Images
Click: **High Risk → Unsafe: X**
→ Shows high-risk images that user explicitly marked as unsafe

### 3. View All Reviewed Medium-Risk Images
Click: **Medium Risk → Reviewed: X**
→ Shows all medium-risk images where user made ANY decision (safe or unsafe)

---

## Data Relationships

### Total
- All images with that risk level (regardless of review status)
- `COUNT(*)`

### Reviewed
- Images where user made a decision (safe OR unsafe)
- `user_decision IS NOT NULL`

### Safe
- Images marked safe by user
- `user_decision = 'safe'`

### Unsafe
- Images marked unsafe by user
- `user_decision = 'unsafe'`

### Mathematical Relationship
```
Reviewed = Safe + Unsafe
Total >= Reviewed
```

---

## Benefits

1. **Granular Filtering** - Filter by both risk level and user decision
2. **Quick Access** - One click to see specific subsets of images
3. **Visual Overview** - See distribution of safe/unsafe decisions at a glance
4. **Consistency** - All numbers are clickable, uniform styling
5. **Workflow Support** - Helps users track review progress

---

## Testing Checklist

- [ ] High Risk Safe link works
- [ ] High Risk Unsafe link works
- [ ] Medium Risk Safe link works
- [ ] Medium Risk Unsafe link works
- [ ] Low Risk Safe link works
- [ ] Low Risk Unsafe link works
- [ ] Numbers are color-coded by risk level
- [ ] Hover effects work on all links
- [ ] Filter correctly shows only matching images
- [ ] Math is correct: Reviewed = Safe + Unsafe


# Pending Check Status Feature

## Overview
When auto-check is enabled, newly uploaded images are now queued for checking with a visual indicator showing they're pending.

## Changes Made

### 1. Queue Status Tracking
**Files Modified:**
- `includes/class-wp-image-guardian.php`
- `includes/class-wp-image-guardian-helpers.php`

**What Changed:**
- When image is uploaded, set `_wp_image_guardian_queued` and `_wp_image_guardian_queued_at` post meta
- Auto-check function (`check_single_upload`) now checks if image was already manually checked
- Queue flags are cleared after check completes (success or failure)
- If manual check is triggered, queue flag is cleared immediately

### 2. Visual Status Indicator
**Files Modified:**
- `includes/class-wp-image-guardian-media.php`
- `assets/css/admin.css`

**What Changed:**
- Added "Pending Check" status badge (blue with hourglass emoji)
- Shows time elapsed since image was queued ("Queued X ago")
- Button text changes to "Check Now (Skip Queue)" when pending
- Animated pulse effect on pending badge

### 3. Status Badge Styles
- ✅ **Checked** - Green badge
- ⏳ **Pending Check** - Blue badge with pulse animation
- ⚠️ **Not Checked** - Yellow badge

## User Experience Flow

### Scenario 1: Auto-Check Enabled
1. User uploads image
2. **Status immediately shows: "Pending Check" (blue badge with hourglass)**
3. Shows "Queued 5 seconds ago" (updates dynamically)
4. After 30 seconds, auto-check runs
5. Status updates to "Checked" with results

### Scenario 2: Manual Check While Pending
1. User uploads image → Status: "Pending Check"
2. User clicks "Check Now (Skip Queue)"
3. Image checked immediately
4. Queue flag cleared automatically
5. When scheduled auto-check runs (30s later), it detects image already checked and skips

### Scenario 3: Manual Check Without Auto-Check
1. User uploads image
2. Status shows: "Not Checked" (yellow badge)
3. Button shows: "Check Image"
4. User clicks to check manually

## Database Schema (No Changes Required)
Uses existing post meta keys:
- `_wp_image_guardian_queued` - Timestamp when queued
- `_wp_image_guardian_queued_at` - MySQL datetime when queued
- `_wp_image_guardian_checked` - Boolean if checked
- `_wp_image_guardian_checked_at` - MySQL datetime when checked

## Benefits

1. **Transparency**: Users know their images are being processed
2. **No Duplicate Checks**: Manual check prevents redundant auto-check
3. **Better UX**: Clear visual feedback on status
4. **Time-Aware**: Shows how long image has been pending
5. **Flexible**: Users can skip queue and check immediately

## CSS Classes Added

```css
.status-badge.pending {
    background: #cfe2ff;
    color: #084298;
    animation: pulse 1.5s ease-in-out infinite;
}

.status-note {
    font-size: 11px;
    color: #666;
    font-style: italic;
}
```

## Testing Checklist

- [ ] Upload image with auto-check enabled → Should show "Pending Check"
- [ ] Wait 30 seconds → Should auto-check and show results
- [ ] Upload image, click "Check Now (Skip Queue)" → Should check immediately
- [ ] Verify no duplicate check when manual check beats auto-check
- [ ] Upload unsupported format → Should clear queue flag
- [ ] Check "Queued X ago" updates correctly

## Notes

- Queue flag is automatically cleared after check (success or failure)
- If auto-check is disabled after queueing, flag is cleared on next attempt
- Queued status only shows if image is NOT already checked
- Manual check always takes precedence over queued check


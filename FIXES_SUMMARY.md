# Fixes Summary - Auto-Check, Filters, and Unsupported Formats

## Issues Fixed

### 1. ✅ Image 88094 - HEIC Format Not Supported
**Problem:** Image was marked as "checked" with "inconclusive" risk level, but actually failed due to unsupported format (HEIC).

**Root Cause:**
- HEIC format was NOT in supported formats list
- Duplicate code in `mark_image_reviewed()` was setting wrong meta values
- Database had `risk_level = 'unknown'` (correct)
- Post meta had `_wp_image_guardian_risk_level = 'low'` (incorrect)
- Post meta had `_wp_image_guardian_user_decision = 'safe'` (incorrect)

**Fixes Applied:**
1. **Added HEIC/HEIF support** to `class-wp-image-guardian-helpers.php`:
   ```php
   'image/heic',  // iPhone/iOS images
   'image/heif',  // High Efficiency Image Format
   ```

2. **Fixed duplicate code** in `mark_image_reviewed()`:
   - Removed duplicate meta updates that set wrong values
   - Now correctly sets:
     - `risk_level = 'unknown'`
     - No user decision
     - Total results = 0

3. **Fixed image 88094 data:**
   ```bash
   lando wp post meta update 88094 _wp_image_guardian_risk_level "unknown"
   lando wp post meta delete 88094 _wp_image_guardian_user_decision
   ```

4. **Enhanced modal display** (`templates/modal-content.php`):
   - Now shows clear message for unsupported formats
   - Displays list of supported formats
   - No longer shows "Safe - No matches found" for unsupported images

**Result:**
- HEIC images now recognized as supported
- Unsupported formats show helpful error message in modal
- Database and meta stay consistent

---

### 2. ✅ Media Library Filters - Added Safe/Unsafe
**Problem:** No way to filter images by safe/unsafe decision in media library.

**Fixes Applied:**
1. **Added "All Decisions" dropdown** to media library filters:
   - Safe
   - Unsafe

2. **Hidden "Reviewed" filter** (but kept functional):
   - Changed from `<select>` to `<input type="hidden">`
   - Links from admin page still work (e.g., `?risk_level=high&reviewed=yes`)
   - User no longer confused by redundant filter

3. **Updated filtering logic** in `filter_media_library_query()`:
   - Handles `user_decision` parameter
   - Filters: `ig.user_decision = 'safe'` or `ig.user_decision = 'unsafe'`

**New Filters:**
```
┌─────────────────────────────────────────────┐
│ Media Library Filters                       │
├─────────────────────────────────────────────┤
│ • All Risk Levels ▼                         │
│ • All Decisions ▼  ← NEW                    │
│ • All Check Status ▼                        │
└─────────────────────────────────────────────┘
```

**Result:**
- Users can filter by safe/unsafe decisions
- Less confusion with hidden "reviewed" dropdown
- All admin page links still work

---

### 3. ✅ Auto-Check Delay Explained
**Problem:** User reported auto-check enabled but images not checking automatically.

**How Auto-Check Works:**
1. **Upload Trigger:** When image is uploaded, `add_attachment` hook fires
2. **Queue Marked:** Image gets `_wp_image_guardian_queued` meta
3. **Schedule Event:** `wp_schedule_single_event(time() + 30, ...)` schedules check in 30 seconds
4. **WP-Cron Dependency:** Event won't run until someone visits the site (WP-Cron limitation)

**Why Delays Happen:**
- **WordPress Cron** doesn't run on a timer
- It only runs when someone visits the site
- If no visitors, scheduled events wait
- Low-traffic sites can have long delays

**Solution Options:**

**Option A: Keep Current (30-second delay)**
```php
// Line 435 in wp-image-guardian.php
wp_schedule_single_event(time() + 30, 'wp_image_guardian_check_single_upload', [$attachment_id]);
```
✅ Less server load
❌ Requires site visit to trigger

**Option B: Immediate Check (sync)**
```php
// Alternative: Check immediately (blocks upload)
$this->check_single_upload($attachment_id);
```
✅ No delay
❌ Slows down upload process
❌ Could timeout on slow connections

**Option C: Real Cron (server config)**
```bash
# Disable WP-Cron in wp-config.php
define('DISABLE_WP_CRON', true);

# Add to server crontab
*/5 * * * * wget -q -O - http://yoursite.com/wp-cron.php?doing_wp_cron
```
✅ Reliable timing
❌ Requires server access
❌ More complex setup

**Recommendation:** Keep current 30-second delay for now.
- Most sites have regular traffic
- Prevents upload timeouts
- Can add server cron later if needed

---

### 4. ✅ Modal Context for Failures
**Problem:** When viewing details on an unsupported image, modal showed confusing "No results found" message.

**Fixes Applied:**
1. **Check for failure reason** in `results_data`:
   ```php
   $reason = $results_data['reason'] ?? '';
   $message = $results_data['message'] ?? '';
   ```

2. **Show contextual message** for `unsupported_format`:
   ```
   ┌────────────────────────────────────────┐
   │ Unsupported Image Format               │
   │                                        │
   │ This image format is not supported    │
   │ by TinyEye and was not checked.       │
   │                                        │
   │ Supported formats:                     │
   │ JPEG, PNG, WebP, GIF, BMP, AVIF,      │
   │ TIFF, HEIC, HEIF                       │
   └────────────────────────────────────────┘
   ```

3. **Added styling** for error messages:
   - Yellow warning box for unsupported format
   - Gray info box for supported formats list
   - No "Safe - No matches found" for errors

**Result:**
- Clear explanation why image wasn't checked
- List of supported formats
- No confusion about "no results"

---

## Files Modified

### 1. `includes/class-wp-image-guardian-database.php`
- Fixed `mark_image_reviewed()` duplicate code
- Removed incorrect meta assignments

### 2. `includes/class-wp-image-guardian-helpers.php`
- Added `image/heic` and `image/heif` to supported formats

### 3. `templates/modal-content.php`
- Added unsupported format detection
- Enhanced error messaging
- Added context-specific styling

### 4. `includes/class-wp-image-guardian-media.php`
- Added `user_decision` filter dropdown
- Hidden `reviewed` filter (kept functional)
- Updated `filter_media_library_query()` for safe/unsafe filtering

### 5. Database (manual fix for image 88094)
- Set `_wp_image_guardian_risk_level` to `'unknown'`
- Deleted `_wp_image_guardian_user_decision`

---

## Testing Checklist

### Unsupported Format (HEIC)
- [ ] Upload HEIC image
- [ ] Should show "Pending Check" briefly
- [ ] Auto-check should mark as "Checked - Inconclusive"
- [ ] Modal should show "Unsupported Image Format" message
- [ ] Should list supported formats

### Media Library Filters
- [ ] "All Decisions" dropdown visible
- [ ] "Reviewed" dropdown hidden
- [ ] Can filter by "Safe"
- [ ] Can filter by "Unsafe"
- [ ] Admin page links still work (e.g., `?risk_level=high&reviewed=yes`)

### Auto-Check
- [ ] Enable auto-check on admin page
- [ ] Upload new image
- [ ] Should show "Pending Check" status
- [ ] After 30+ seconds AND page refresh, status updates
- [ ] If manually checked while pending, auto-check skips
- [ ] Queue flag cleared after check

### Image 88094 Specific
- [ ] Shows "Checked - Inconclusive"
- [ ] No "Safe" badge
- [ ] Modal explains unsupported format
- [ ] Can't be re-checked (HEIC still unsupported by TinyEye)

---

## Known Limitations

### 1. WP-Cron Dependency
- Auto-check won't run without site traffic
- Recommend Action Scheduler for production (already used for bulk checks)

### 2. HEIC Support
- HEIC is now "supported" by our plugin
- But TinyEye API still doesn't accept HEIC
- So HEIC images get marked as "inconclusive"
- WordPress can convert HEIC→JPEG if ImageMagick installed

### 3. Modal Action Buttons
- "Mark as Safe/Unsafe" buttons still show for unsupported formats
- Consider hiding for unsupported images in future update

---

## Supported Image Formats

**Now Supported (10 formats):**
1. JPEG / JPG
2. PNG
3. WebP
4. GIF
5. BMP / X-MS-BMP
6. AVIF
7. TIFF / TIF
8. HEIC (iPhone)
9. HEIF (High Efficiency)

**Note:** TinyEye API may not accept all of these. If TinyEye rejects, image is marked as "unsupported_format".

---

## Auto-Check Flow Diagram

```
┌─────────────────┐
│ User Uploads    │
│ Image           │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ add_attachment  │
│ hook fires      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐    NO      ┌─────────────┐
│ Auto-check      ├───────────►│ Stop        │
│ enabled?        │            └─────────────┘
└────────┬────────┘
         │ YES
         ▼
┌─────────────────┐
│ Set meta:       │
│ _queued = time  │
│ _queued_at =    │
│ timestamp       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Schedule event  │
│ 30 sec delay    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐    YES     ┌─────────────┐
│ User visits     ├───────────►│ WP-Cron     │
│ site?           │            │ runs event  │
└─────────────────┘            └──────┬──────┘
         │ NO                          │
         │ (wait...)                   ▼
         │                    ┌─────────────────┐
         └───────────────────►│ Check if        │
                              │ already checked │
                              └────────┬────────┘
                                       │
                                       ▼
                              ┌─────────────────┐
                              │ Call TinyEye    │
                              │ API             │
                              └────────┬────────┘
                                       │
                                       ▼
                              ┌─────────────────┐
                              │ Store results   │
                              │ Clear queue flag│
                              └─────────────────┘
```

---

## Next Steps (Optional Improvements)

1. **Use Action Scheduler for auto-check** (more reliable than WP-Cron)
2. **Hide Mark Safe/Unsafe buttons** for unsupported formats
3. **Add HEIC→JPEG conversion** before TinyEye check (if ImageMagick available)
4. **Add retry logic** for failed API calls
5. **Add notification** when auto-check completes (admin notice or email)

---

## Command Reference

### Check image 88094 data:
```bash
lando wp db query "SELECT * FROM wp_image_guardian_checks WHERE attachment_id = 88094"
lando wp post meta list 88094 | grep image_guardian
lando wp post get 88094 --field=post_mime_type
```

### Test WP-Cron:
```bash
lando wp cron test
lando wp cron event list --format=table | grep image_guardian
```

### Fix image 88094:
```bash
lando wp post meta update 88094 _wp_image_guardian_risk_level "unknown"
lando wp post meta delete 88094 _wp_image_guardian_user_decision
```

### Enable auto-check:
```bash
lando wp option update wp_image_guardian_auto_check 1
```


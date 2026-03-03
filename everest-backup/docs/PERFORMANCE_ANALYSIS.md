# Slow Backup Download During Restore - Performance Analysis

## Problem Summary
Backup files download slowly during the restore/clone process, causing extended wait times for users.

## Root Causes Identified

### 1. **Small Chunk Size** (Primary Issue)

**Location:** [`inc/functions.php:72-250`](file:///Users/n1tech/Local Sites/everest-backup/app/public/wp-content/plugins/everest-backup/inc/functions.php#L72-L250) - `everest_backup_download_file()`

**Current Implementation:**
```php
if ( everest_backup_is_localhost() ) {
    $timeout    = 30;
    $range_size = 2 * MB_IN_BYTES;  // 2 MB chunks for localhost
} else {
    $timeout    = 20;
    $range_size = 20 * MB_IN_BYTES; // 20 MB chunks for live sites
}
```

**Problem:**
- Downloads only **2 MB** per request on localhost
- Downloads only **20 MB** per request on live servers
- Each chunk requires a **separate HTTP request** with full overhead
- For a 1 GB backup file:
  - **Localhost:** 500 requests (1000 MB ÷ 2 MB)
  - **Live server:** 50 requests (1000 MB ÷ 20 MB)

**Impact:**
- Each request has overhead: DNS lookup, TCP handshake, SSL negotiation, HTTP headers
- Server must process 50-500 separate requests
- PHP script execution time limits require process to die and restart between chunks
- Progress tracking and logging add additional overhead

### 2. **Process Termination Between Chunks**

**Location:** Lines 243-246

```php
if ( ! $complete ) {
    set_transient( 'everest_backup_migrate_clone_download', true );
    die();  // ⚠️ Script terminates after each chunk
}
```

**Problem:**
- Script terminates after downloading each chunk
- Next chunk requires a new AJAX request
- Each new request has initialization overhead
- State must be saved/loaded between requests

### 3. **Low Timeout Values**

**Current Settings:**
- Localhost: 30 seconds timeout
- Live server: 20 seconds timeout

**Problem:**
- Conservative timeouts force smaller chunk sizes
- Doesn't account for slow network connections
- May cause premature failures on slower servers

### 4. **Retry Logic Overhead**

**Location:** Lines 169-214

```php
if (!$success && $error) {
    $retry = get_transient('everest_backup_migrate_clone_download_retry');
    $retry = $retry ? ( $retry + 1) : 1;
    if ( $retry > 3) {
        // Fail after 3 retries
    }
}
```

**Problem:**
- Each failed chunk retries up to 3 times
- Network hiccups multiply download time
- No exponential backoff strategy

## Performance Calculations

### Current Performance (20 MB chunks on live server)

For a **1 GB backup file**:
- **Number of chunks:** 1000 MB ÷ 20 MB = **50 chunks**
- **Request overhead per chunk:** ~2-5 seconds (AJAX, cURL init, SSL handshake)
- **Total overhead:** 50 × 3 seconds = **150 seconds (2.5 minutes)**
- **Actual download time:** Depends on bandwidth
- **Total time:** Download time + 2.5 minutes overhead

### Optimized Performance (100 MB chunks)

For a **1 GB backup file**:
- **Number of chunks:** 1000 MB ÷ 100 MB = **10 chunks**
- **Request overhead per chunk:** ~2-5 seconds
- **Total overhead:** 10 × 3 seconds = **30 seconds**
- **Total time:** Download time + 30 seconds overhead
- **Improvement:** **80% reduction in overhead** (120 seconds saved)

## Recommended Solutions

### Solution 1: Increase Chunk Size (Quick Fix) ⭐

**Priority:** HIGH
**Effort:** LOW
**Impact:** HIGH

**Implementation:**
```php
if ( everest_backup_is_localhost() ) {
    $timeout    = 60;              // Increased from 30
    $range_size = 10 * MB_IN_BYTES; // Increased from 2 MB to 10 MB
} else {
    $timeout    = 90;               // Increased from 20
    $range_size = 100 * MB_IN_BYTES; // Increased from 20 MB to 100 MB
}
```

**Benefits:**
- 5x fewer requests (50 → 10 for 1GB file)
- 80% reduction in overhead
- Minimal code changes
- Backward compatible

**Risks:**
- May timeout on very slow connections
- Requires more memory per request

### Solution 2: Adaptive Chunk Sizing (Medium Fix)

**Priority:** MEDIUM
**Effort:** MEDIUM
**Impact:** HIGH

**Implementation:**
```php
// Start with small chunks, increase if successful
$base_chunk_size = 20 * MB_IN_BYTES;
$successful_downloads = get_transient('ebwp_successful_chunk_count');

if ($successful_downloads > 5) {
    $range_size = min(100 * MB_IN_BYTES, $base_chunk_size * 2);
} else {
    $range_size = $base_chunk_size;
}
```

**Benefits:**
- Adapts to connection quality
- Starts conservative, speeds up if stable
- Handles slow connections gracefully

### Solution 3: Stream Download Without Chunking (Advanced)

**Priority:** LOW
**Effort:** HIGH
**Impact:** VERY HIGH

**Implementation:**
Use PHP streams with progress callbacks instead of range requests:
```php
$context = stream_context_create([
    'http' => [
        'timeout' => 300,
        'method' => 'GET',
    ],
    'notification' => 'download_progress_callback'
]);

$fp_source = fopen($source, 'r', false, $context);
$fp_dest = fopen($destination, 'w');
stream_copy_to_stream($fp_source, $fp_dest);
```

**Benefits:**
- Single continuous download
- No chunking overhead
- Faster for large files
- Better resource utilization

**Risks:**
- May exceed PHP execution time limits
- Requires server configuration changes
- More complex error handling

### Solution 4: Background Download with WP-Cron

**Priority:** LOW
**Effort:** HIGH
**Impact:** MEDIUM

**Implementation:**
- Schedule download as background task
- Use larger chunks or streaming
- Report progress via transients
- No user-facing timeout issues

**Benefits:**
- Not limited by browser timeouts
- Can use optimal chunk sizes
- Better user experience

**Risks:**
- More complex implementation
- Requires WP-Cron to be working
- Harder to debug

## Comparison Table

| Solution | Effort | Impact | Time Saved (1GB) | Recommended |
|----------|--------|--------|------------------|-------------|
| **Increase Chunk Size** | Low | High | ~2 minutes | ✅ Yes |
| **Adaptive Chunking** | Medium | High | ~2-3 minutes | ✅ Yes |
| **Stream Download** | High | Very High | ~3-4 minutes | ⚠️ Maybe |
| **Background Download** | High | Medium | ~2-3 minutes | ⚠️ Maybe |

## Immediate Action Plan

### Phase 1: Quick Win (Implement Now) ✅

1. **Increase chunk sizes** in `inc/functions.php`:
   - Localhost: 2 MB → 10 MB
   - Live server: 20 MB → 100 MB
   - Increase timeouts accordingly

2. **Test thoroughly:**
   - Test on slow connections
   - Test with large files (>1GB)
   - Monitor error rates

### Phase 2: Optimization (Next Release)

1. **Implement adaptive chunking**
2. **Add connection speed detection**
3. **Optimize retry logic with exponential backoff**

### Phase 3: Advanced (Future)

1. **Consider streaming for very large files**
2. **Implement background download option**
3. **Add resume capability for interrupted downloads**

## Additional Optimizations

### 1. Reduce Logging Overhead

**Current:** Logs written on every chunk
**Optimization:** Batch log writes, log only every 5-10 chunks

### 2. Optimize Progress Updates

**Current:** Updates sent on every chunk
**Optimization:** Throttle updates to every 2-3 seconds

### 3. Connection Reuse

**Current:** New cURL connection per chunk
**Optimization:** Reuse cURL handle with keep-alive

### 4. Parallel Downloads (Advanced)

For very large files, consider downloading multiple ranges in parallel (requires careful implementation).

## Monitoring & Metrics

After implementing changes, track:
- Average download time per GB
- Number of failed downloads
- Number of retries
- Timeout occurrences
- User-reported issues

## Code Locations Reference

| Component | File | Lines |
|-----------|------|-------|
| **Download Function** | `inc/functions.php` | 72-250 |
| **Clone Download** | `inc/modules/migration-clone/class-cloner.php` | 87-127 |
| **Chunk Size Config** | `inc/functions.php` | 73-79 |
| **Timeout Config** | `inc/functions.php` | 74, 77 |
| **Retry Logic** | `inc/functions.php` | 169-214 |
| **Progress Tracking** | `inc/functions.php` | 215-236 |

## Testing Checklist

Before deploying changes:

- [ ] Test with 100 MB file
- [ ] Test with 1 GB file
- [ ] Test with 5 GB file
- [ ] Test on localhost
- [ ] Test on live server
- [ ] Test on slow connection (throttled)
- [ ] Test with connection interruption
- [ ] Test retry mechanism
- [ ] Monitor memory usage
- [ ] Monitor CPU usage
- [ ] Check error logs

## Conclusion

The primary cause of slow downloads is the **small chunk size** combined with **process termination between chunks**. Increasing the chunk size from 20 MB to 100 MB will provide an immediate **80% reduction in overhead**, significantly improving download speeds with minimal risk and effort.

**Recommended First Step:** Implement Solution 1 (Increase Chunk Size) immediately for quick performance gains.

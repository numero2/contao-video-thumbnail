'use strict';

(function () {

    const DELETE_TOKEN = '__delete__';

    /**
     * Formats seconds as m:ss.mmm (e.g. 0:20.120).
     */
    function formatTime(t) {
        if (!isFinite(t) || t < 0) t = 0;
        const ms = Math.round(t * 1000);
        const m  = Math.floor(ms / 60000);
        const s  = Math.floor((ms % 60000) / 1000);
        return m + ':' + String(s).padStart(2, '0') + '.' + String(ms % 1000).padStart(3, '0');
    }

    /**
     * Initialise one .vt-widget block.
     */
    function initWidget(widget) {

        const step      = parseFloat(widget.dataset.step    || '0.04');
        const quality   = parseFloat(widget.dataset.quality || '0.85');
        const savedTime = ('time' in widget.dataset) ? parseFloat(widget.dataset.time) : null;

        const player    = widget.querySelector('.vt-player');
        const video     = widget.querySelector('video');
        const canvas    = widget.querySelector('.vt-canvas');
        const input     = widget.querySelector('.vt-value');
        const inputTime = widget.querySelector('.vt-value-time');

        if (!player || !video || !canvas || !input) return;

        const btnPlay      = widget.querySelector('.vt-play');
        const seek         = widget.querySelector('.vt-seek');
        const marker       = widget.querySelector('.vt-marker');
        const timeCur      = widget.querySelector('.vt-time-current');
        const timeDur      = widget.querySelector('.vt-time-duration');
        const badge        = widget.querySelector('.vt-current-badge');
        const badgeTime    = widget.querySelector('.vt-badge-time');
        const sidebar      = widget.querySelector('.vt-sidebar');
        const preview      = widget.querySelector('.vt-preview');
        const timestampBox = widget.querySelector('.vt-timestamp');
        const timestampOut = widget.querySelector('.vt-timestamp-value');
        const btnCapture   = widget.querySelector('.vt-capture');
        const btnClear     = widget.querySelector('.vt-clear');
        const btnPrev      = widget.querySelector('.vt-prev-frame');
        const btnNext      = widget.querySelector('.vt-next-frame');
        const unsavedHint  = widget.querySelector('.vt-unsaved');
        const flash        = widget.querySelector('.vt-flash');

        const ctx = canvas.getContext('2d');

        // Position of the current (saved or freshly captured) thumbnail —
        // null when none exists. Drives badge visibility and the marker.
        let thumbTime = (savedTime !== null && isFinite(savedTime)) ? savedTime : null;

        // ---- Time display / seek bar -----------------------------------

        // Only show the badge while the playhead actually sits on the
        // thumbnail position (within half a frame step).
        function updateBadge() {
            if (!badge) return;
            badge.hidden = thumbTime === null || Math.abs(video.currentTime - thumbTime) > step / 2;
        }

        function updateTimeUI() {
            const t = video.currentTime;
            timeCur && (timeCur.textContent = formatTime(t));
            if (seek && video.duration) {
                seek.value = t;
                seek.style.setProperty('--vt-progress', (t / video.duration * 100) + '%');
                seek.setAttribute('aria-valuetext', formatTime(t) + ' / ' + formatTime(video.duration));
            }
            updateBadge();
        }

        const markerLabel = marker ? marker.getAttribute('aria-label') : '';

        function setMarker(t) {
            if (!marker) return;
            if (t === null || !video.duration) {
                marker.hidden = true;
                return;
            }
            marker.style.left = Math.min(100, t / video.duration * 100) + '%';
            marker.setAttribute('aria-label', markerLabel + ' (' + formatTime(t) + ')');
            marker.hidden = false;
        }

        function onMetadataReady() {
            seek && (seek.max = video.duration);
            timeDur && (timeDur.textContent = formatTime(video.duration));

            // Jump straight to the position the current thumbnail was captured at
            if (thumbTime !== null) {
                video.currentTime = Math.min(thumbTime, video.duration);
                badgeTime && (badgeTime.textContent = formatTime(thumbTime));
                timestampOut && (timestampOut.textContent = formatTime(thumbTime));
                setMarker(thumbTime);
            }

            updateTimeUI();
        }

        // The metadata may already be loaded before this listener is attached
        // (Turbo restore, fast local network) — then the event never fires.
        if (video.readyState >= HTMLMediaElement.HAVE_METADATA) {
            onMetadataReady();
        } else {
            video.addEventListener('loadedmetadata', onMetadataReady, { once: true });
        }

        video.addEventListener('timeupdate', updateTimeUI);
        video.addEventListener('seeked', updateTimeUI);

        seek && seek.addEventListener('input', function () {
            video.pause();
            video.currentTime = parseFloat(seek.value);
        });

        // Clicking the marker jumps exactly to the stored thumbnail position
        marker && marker.addEventListener('click', function () {
            if (thumbTime === null) return;
            video.pause();
            video.currentTime = thumbTime;
        });

        // ---- Play / pause ------------------------------------------------

        function togglePlay() {
            video.paused ? video.play() : video.pause();
        }

        video.addEventListener('play', function () {
            player.classList.add('vt-playing');
            btnPlay && btnPlay.setAttribute('aria-pressed', 'true');
        });

        video.addEventListener('pause', function () {
            player.classList.remove('vt-playing');
            btnPlay && btnPlay.setAttribute('aria-pressed', 'false');
        });

        btnPlay && btnPlay.addEventListener('click', togglePlay);
        video.addEventListener('click', togglePlay);

        // ---- Frame stepping ------------------------------------------------

        function seekBy(delta) {
            video.pause();
            const max = video.duration || Infinity;
            video.currentTime = Math.max(0, Math.min(video.currentTime + delta, max));
        }

        btnPrev && btnPrev.addEventListener('click', function () { seekBy(-step); });
        btnNext && btnNext.addEventListener('click', function () { seekBy(+step); });

        // ---- Keyboard shortcuts (scoped to the focused player) --------------

        player.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                seekBy(-step);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                seekBy(+step);
            } else if (e.key === ' ' && !e.target.closest('button')) {
                e.preventDefault();
                togglePlay();
            }
        });

        // ---- Capture -------------------------------------------------------

        // Camera-flash effect on the video — restart the animation even when
        // capturing repeatedly in quick succession.
        function flashVideo() {
            if (!flash) return;
            flash.classList.remove('vt-flashing');
            void flash.offsetWidth; // force reflow so the animation restarts
            flash.classList.add('vt-flashing');
        }

        flash && flash.addEventListener('animationend', function () {
            flash.classList.remove('vt-flashing');
        });

        function captureFrame() {
            canvas.width  = video.videoWidth  || 1280;
            canvas.height = video.videoHeight || 720;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', quality);
            const t       = video.currentTime;
            const label   = formatTime(t);

            input.value = dataUrl;
            inputTime && (inputTime.value = t.toFixed(3));
            thumbTime = t;

            flashVideo();
            showPreview(dataUrl);
            timestampOut && (timestampOut.textContent = label);
            timestampBox && (timestampBox.hidden = false);
            badgeTime && (badgeTime.textContent = label);
            updateBadge();
            setMarker(t);
            showUnsavedHint();
        }

        // Wait for the frame to actually be painted after a seek before capturing.
        function captureAfterSeek() {
            if (typeof video.requestVideoFrameCallback === 'function') {
                video.requestVideoFrameCallback(captureFrame);
            } else {
                video.addEventListener('seeked', captureFrame, { once: true });
            }
        }

        btnCapture && btnCapture.addEventListener('click', function () {
            if (video.paused || video.ended) {
                captureFrame();
            } else {
                video.pause();
                captureAfterSeek();
            }
        });

        // ---- Clear ---------------------------------------------------------

        btnClear && btnClear.addEventListener('click', function () {
            input.value = DELETE_TOKEN;
            inputTime && (inputTime.value = '');
            thumbTime = null;

            hidePreview();
            timestampBox && (timestampBox.hidden = true);
            updateBadge();
            setMarker(null);
            showUnsavedHint();
        });

        // ---- Sidebar helpers -------------------------------------------------

        function showPreview(src) {
            sidebar && (sidebar.hidden = false);
            if (!preview) return;
            let img = preview.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                const title = widget.querySelector('.vt-sidebar-title');
                img.alt = title ? title.textContent : '';
                preview.appendChild(img);
            }
            img.src = src;
            preview.hidden = false;
            btnClear && (btnClear.hidden = false);
        }

        function hidePreview() {
            if (preview) preview.hidden = true;
            btnClear && (btnClear.hidden = true);
        }

        function showUnsavedHint() {
            if (!unsavedHint) return;
            const wasHidden = unsavedHint.hidden;
            unsavedHint.hidden = false;

            // Screen readers don't reliably announce a live region that merely
            // becomes visible — re-insert the text to trigger a mutation.
            if (wasHidden) {
                const text = unsavedHint.textContent;
                unsavedHint.textContent = '';
                requestAnimationFrame(function () {
                    unsavedHint.textContent = text;
                });
            }
        }
    }

    // ---- Bootstrap ---------------------------------------------------------

    // The Contao 5 backend navigates via Hotwire Turbo: scripts get merged
    // into the <head> after the document has finished loading, so
    // DOMContentLoaded never fires for them. Initialise immediately when the
    // DOM is already available and re-initialise on every Turbo visit.
    function init() {

        document.querySelectorAll('.vt-widget:not([data-vt-initialized])').forEach(function (widget) {
            widget.dataset.vtInitialized = '1';
            initWidget(widget);
        });
    }

    if (document.readyState !== 'loading') init();
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);

    // Turbo caches a clone of the page (event listeners are lost) — drop the
    // init markers before caching so restored pages get re-initialised.
    document.addEventListener('turbo:before-cache', function () {

        document.querySelectorAll('.vt-widget[data-vt-initialized]').forEach(function (widget) {
            delete widget.dataset.vtInitialized;
        });
    });

})();

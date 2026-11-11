/* =============================================================
   MEDICINE & VACCINE AVAILABILITY SLIDER (index.php)
   ---------------------------------------------------------------
   index.php now renders every one of its (up to 16, 4-per-slide)
   medicines/vaccines into one ".med-slide" per set of 4, all sitting
   side by side inside "#medSliderTrack" — this just slides that track
   left/right via its own Prev/Next buttons (and the dot indicators),
   wrapping around at either end, so the widget doesn't just look like
   one fixed, unchanging set of 4 items. Nothing here fetches data;
   every slide's real content is already in the page from PHP.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const track = document.getElementById('medSliderTrack');
    if (!track) return;

    const prevBtn = document.getElementById('medSliderPrev');
    const nextBtn = document.getElementById('medSliderNext');
    const dotsTrack = document.getElementById('medSliderDots');
    const dots = document.querySelectorAll('#medSliderDots .med-slider-dot');
    const slideCount = track.children.length;
    if (slideCount <= 1) return; // nothing to slide to — Prev/Next/dots aren't even rendered in this case

    // Dots show at most this many at once (see home.css's own
    // .med-slider-dots-viewport, sized to match in index.php) — with
    // dozens of real medicines/vaccines there can be dozens of slides,
    // so the window slides along to keep the current dot in view
    // instead of listing one dot per slide.
    const DOT_WINDOW = 4;
    const DOT_SLOT_WIDTH = 14;

    let current = 0;

    function updateDotsWindow() {
        if (!dotsTrack || dots.length <= DOT_WINDOW) return;
        const maxOffset = dots.length - DOT_WINDOW;
        const offset = Math.min(Math.max(current - Math.floor(DOT_WINDOW / 2), 0), maxOffset);
        dotsTrack.style.transform = 'translateX(-' + (offset * DOT_SLOT_WIDTH) + 'px)';
    }

    function goTo(index) {
        current = (index + slideCount) % slideCount;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        dots.forEach(function (dot, i) { dot.classList.toggle('active', i === current); });
        updateDotsWindow();
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });
    dots.forEach(function (dot) {
        dot.addEventListener('click', function () { goTo(parseInt(dot.dataset.slide, 10)); });
    });
});

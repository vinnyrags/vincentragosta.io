/**
 * Nous Signal Carrier — hover readout, touch scrubbing, and bar links.
 *
 * The pointer picks the nearest bar by x (bars are a few pixels wide, too
 * thin to hit), but only within one day's slot of it: the silence after the
 * last transmission is dead space, not part of the last bar.
 *
 * Mouse: hover shows the floating readout; a click opens the bar.
 * Touch and pen: a fingertip covers weeks of bars, so there is no tapping a
 * bar. Dragging sideways scrubs (the slot above the plot follows, with a
 * hairline above the finger); lifting holds the pick and reveals a "Read"
 * link, the only thing that navigates. Dragging into the silence or pressing
 * anywhere else clears it. Vertical swipes still scroll (touch-action: pan-y).
 *
 * Keyboard users have the archive index below, so the bars are not focusable.
 */

/**
 * Index of the bar whose center is nearest to x, given each bar's center.
 * @param {number[]} centers - ascending
 * @param {number} x
 * @param {number} reach - furthest a bar may be from x and still count
 * @returns {number} -1 when no bar is within reach
 */
export function nearestBar(centers, x, reach = Infinity) {
    let best = -1;
    let distance = Infinity;

    centers.forEach((center, index) => {
        const d = Math.abs(center - x);
        if (d < distance) {
            best = index;
            distance = d;
        }
    });

    return distance <= reach ? best : -1;
}

/**
 * Width of one day's slot: the smallest gap between neighboring bar centers
 * (a missed day leaves a wider gap, never a narrower one).
 * @param {number[]} centers - ascending
 * @param {number} fallback - used when there is a single bar
 */
export function slotWidth(centers, fallback) {
    let slot = Infinity;

    for (let i = 1; i < centers.length; i++) {
        slot = Math.min(slot, centers[i] - centers[i - 1]);
    }

    return Number.isFinite(slot) ? slot : fallback;
}

/**
 * Left offset that centers a box of `width` on `center`, kept inside [0, bound].
 */
export function clampLeft(center, width, bound) {
    return Math.max(0, Math.min(center - width / 2, bound - width));
}

/**
 * Fill the readout: "2026.08.17 Guaranteed by the Seller · 5 min".
 * @param {HTMLElement} readout
 * @param {DOMStringMap} bar - the rect's dataset
 */
export function fillReadout(readout, bar) {
    const date = document.createElement('strong');
    date.textContent = bar.date;
    readout.replaceChildren(date, ` ${bar.title} · ${bar.minutes} min`);
}

/**
 * Fill the touch slot with a bar and point its link at it.
 * @param {HTMLElement} root - [data-carrier]
 * @param {DOMStringMap} bar - the rect's dataset
 */
export function fillPick(root, bar) {
    root.querySelector('[data-carrier-date]').textContent = bar.date;
    root.querySelector('[data-carrier-minutes]').textContent = `· ${bar.minutes} min`;
    root.querySelector('[data-carrier-title]').textContent = bar.title;
    root.querySelector('[data-carrier-go]').href = bar.link;
}

/**
 * @param {HTMLElement} root - [data-carrier]
 * @param {{navigate?: (url: string) => void}} options
 */
export function initCarrier(root, { navigate = (url) => window.location.assign(url) } = {}) {
    const plot = root.querySelector('[data-carrier-plot]');
    const svg = plot?.querySelector('svg');
    const readout = root.querySelector('[data-carrier-readout]');
    const pickBox = root.querySelector('[data-carrier-pick]');
    const go = root.querySelector('[data-carrier-go]');
    const hair = root.querySelector('[data-carrier-hair]');
    const rects = svg ? Array.from(svg.querySelectorAll('rect')) : [];

    if (!rects.length || !readout || !pickBox || !go || !hair) {
        return;
    }

    let active = -1;
    let scrubbing = false;

    const centers = () => {
        const origin = plot.getBoundingClientRect().left;
        return rects.map((rect) => {
            const box = rect.getBoundingClientRect();
            return box.left - origin + box.width / 2;
        });
    };

    const pick = (event) => {
        const all = centers();
        const x = event.clientX - plot.getBoundingClientRect().left;
        const reach = slotWidth(all, rects[0].getBoundingClientRect().width);
        const index = nearestBar(all, x, reach);
        return { index, center: all[index] };
    };

    const activate = (index) => {
        if (active > -1) {
            rects[active].classList.remove('is-active');
        }
        active = index;
        if (index > -1) {
            rects[index].classList.add('is-active');
        }
    };

    // Mouse: the floating readout.
    const show = (index, center) => {
        if (index === active) {
            return;
        }
        activate(index);
        fillReadout(readout, rects[index].dataset);
        readout.hidden = false;
        readout.style.left = `${clampLeft(center, readout.offsetWidth, plot.clientWidth)}px`;
    };

    const hide = () => {
        activate(-1);
        readout.hidden = true;
    };

    // Touch: the slot, in one of three states (rest, scrubbing, held).
    const clear = () => {
        activate(-1);
        root.classList.remove('is-scrubbing', 'is-held');
        pickBox.hidden = true;
        go.hidden = true;
    };

    const scrubTo = (index, center) => {
        if (index < 0) {
            clear();
            return;
        }
        root.classList.remove('is-held');
        root.classList.add('is-scrubbing');
        if (index !== active) {
            activate(index);
            fillPick(root, rects[index].dataset);
        }
        pickBox.hidden = false;
        go.hidden = true;
        hair.style.left = `${center}px`;
    };

    const hold = () => {
        root.classList.remove('is-scrubbing');
        if (active < 0) {
            clear();
            return;
        }
        root.classList.add('is-held');
        go.hidden = false;
    };

    svg.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse') {
            return;
        }
        scrubbing = true;
        svg.setPointerCapture?.(event.pointerId);
        const { index, center } = pick(event);
        scrubTo(index, center);
    });

    svg.addEventListener('pointermove', (event) => {
        const { index, center } = pick(event);

        if (event.pointerType !== 'mouse') {
            if (scrubbing) {
                scrubTo(index, center);
            }
            return;
        }

        if (index > -1) {
            show(index, center);
        } else {
            hide();
        }
    });

    svg.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'mouse') {
            hide();
        }
    });

    svg.addEventListener('pointerup', (event) => {
        if (event.pointerType !== 'mouse') {
            scrubbing = false;
            hold();
            return;
        }
        const { index } = pick(event);
        if (index < 0) {
            hide();
            return;
        }
        navigate(rects[index].dataset.link);
    });

    // The browser took the gesture (a vertical scroll): drop the pick.
    svg.addEventListener('pointercancel', () => {
        scrubbing = false;
        clear();
    });

    // Pressing anywhere outside the carrier clears a held pick.
    document.addEventListener('pointerdown', (event) => {
        if (root.classList.contains('is-held') && !root.contains(event.target)) {
            clear();
        }
    });
}

function init() {
    document.querySelectorAll('[data-carrier]').forEach((root) => initCarrier(root));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}

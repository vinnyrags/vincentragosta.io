import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
    nearestBar,
    slotWidth,
    clampLeft,
    fillReadout,
    fillPick,
    initCarrier,
} from '../../../../../../src/Providers/Blog/blocks/nous-carrier/view.js';

/**
 * Build a carrier plot with one rect per bar. jsdom has no layout, so each
 * rect's box is stubbed: bar i is centered at 10 + 20i px (20px slots) in a
 * 200px plot, leaving 100px of silence after the last bar.
 * @param {number} count
 */
function createCarrier(count) {
    const rects = Array.from(
        { length: count },
        (_, i) =>
            `<rect data-date="2026.03.0${i + 1}" data-title="Signal &amp; ${i + 1}" data-minutes="${i + 4}" data-link="https://example.com/s${i + 1}/"></rect>`
    ).join('');

    document.body.innerHTML = `
        <div data-carrier>
            <div>
                <div data-carrier-pick hidden>
                    <span data-carrier-date></span><span data-carrier-minutes></span><strong data-carrier-title></strong>
                    <a data-carrier-go href="#" hidden>Read</a>
                </div>
            </div>
            <div data-carrier-plot>
                <svg>${rects}</svg>
                <span data-carrier-hair></span>
                <p data-carrier-readout hidden></p>
            </div>
        </div>
        <p id="elsewhere">Elsewhere</p>`;

    const root = document.querySelector('[data-carrier]');
    const plot = root.querySelector('[data-carrier-plot]');
    plot.getBoundingClientRect = () => ({ left: 0, width: 200 });
    Object.defineProperty(plot, 'clientWidth', { value: 200 });
    plot.querySelectorAll('rect').forEach((rect, i) => {
        rect.getBoundingClientRect = () => ({ left: 8 + 20 * i, width: 4 });
    });

    return root;
}

function pointer(target, type, clientX, pointerType = 'mouse') {
    const event = new Event(type, { bubbles: true });
    Object.assign(event, { clientX, pointerType });
    target.dispatchEvent(event);
}

describe('nearestBar', () => {
    it('picks the bar whose center is closest', () => {
        expect(nearestBar([10, 30, 50], 0)).toBe(0);
        expect(nearestBar([10, 30, 50], 38)).toBe(1);
        expect(nearestBar([10, 30, 50], 99)).toBe(2);
    });

    it('returns -1 with no bars', () => {
        expect(nearestBar([], 10)).toBe(-1);
    });

    it('returns -1 when the nearest bar is out of reach', () => {
        expect(nearestBar([10, 30, 50], 70, 20)).toBe(2);
        expect(nearestBar([10, 30, 50], 71, 20)).toBe(-1);
    });
});

describe('slotWidth', () => {
    it('is the smallest gap between neighboring centers', () => {
        expect(slotWidth([10, 30, 70, 90], 4)).toBe(20);
    });

    it('falls back with a single bar', () => {
        expect(slotWidth([10], 4)).toBe(4);
    });
});

describe('clampLeft', () => {
    it('centers the box on the point', () => {
        expect(clampLeft(50, 20, 100)).toBe(40);
    });

    it('keeps the box inside both edges', () => {
        expect(clampLeft(5, 20, 100)).toBe(0);
        expect(clampLeft(98, 20, 100)).toBe(80);
    });
});

describe('fillReadout', () => {
    it('writes the date, title and read time as text', () => {
        const readout = document.createElement('p');
        fillReadout(readout, { date: '2026.08.17', title: '<b>Guaranteed</b>', minutes: '5' });

        expect(readout.querySelector('strong').textContent).toBe('2026.08.17');
        expect(readout.textContent).toBe('2026.08.17 <b>Guaranteed</b> · 5 min');
        expect(readout.querySelector('b')).toBeNull();
    });
});

describe('fillPick', () => {
    it('writes the bar as text and points the link at it', () => {
        const root = createCarrier(1);
        fillPick(root, { date: '2026.08.17', title: '<b>Guaranteed</b>', minutes: '5', link: 'https://example.com/g/' });

        expect(root.querySelector('[data-carrier-date]').textContent).toBe('2026.08.17');
        expect(root.querySelector('[data-carrier-minutes]').textContent).toBe('· 5 min');
        expect(root.querySelector('[data-carrier-title]').textContent).toBe('<b>Guaranteed</b>');
        expect(root.querySelector('[data-carrier-go]').getAttribute('href')).toBe('https://example.com/g/');
    });
});

describe('initCarrier with a mouse', () => {
    let root;
    let svg;
    let readout;
    let navigate;

    beforeEach(() => {
        root = createCarrier(5);
        svg = root.querySelector('svg');
        readout = root.querySelector('[data-carrier-readout]');
        navigate = vi.fn();
        initCarrier(root, { navigate });
    });

    it('shows the nearest bar on hover and marks it active', () => {
        pointer(svg, 'pointermove', 52);

        expect(readout.hidden).toBe(false);
        expect(readout.textContent).toBe('2026.03.03 Signal & 3 · 6 min');
        expect(svg.querySelectorAll('rect.is-active')).toHaveLength(1);
        expect(svg.querySelectorAll('rect')[2].classList.contains('is-active')).toBe(true);
    });

    it('hides the readout when the mouse leaves', () => {
        pointer(svg, 'pointermove', 52);
        pointer(svg, 'pointerleave', 52);

        expect(readout.hidden).toBe(true);
        expect(svg.querySelector('rect.is-active')).toBeNull();
    });

    it('opens the transmission on a click', () => {
        pointer(svg, 'pointerup', 90);

        expect(navigate).toHaveBeenCalledWith('https://example.com/s5/');
    });

    it('ignores the silence after the last bar', () => {
        pointer(svg, 'pointermove', 150);
        expect(readout.hidden).toBe(true);

        pointer(svg, 'pointerup', 150);
        expect(navigate).not.toHaveBeenCalled();
    });

    it('hides the readout when the mouse moves from a bar into the silence', () => {
        pointer(svg, 'pointermove', 90);
        expect(readout.hidden).toBe(false);

        pointer(svg, 'pointermove', 150);
        expect(readout.hidden).toBe(true);
        expect(svg.querySelector('rect.is-active')).toBeNull();
    });

    it('never uses the touch slot', () => {
        pointer(svg, 'pointermove', 52);

        expect(root.querySelector('[data-carrier-pick]').hidden).toBe(true);
        expect(root.classList.contains('is-scrubbing')).toBe(false);
    });
});

describe('initCarrier with touch', () => {
    let root;
    let svg;
    let pick;
    let go;
    let navigate;

    const title = () => root.querySelector('[data-carrier-title]').textContent;

    beforeEach(() => {
        root = createCarrier(5);
        svg = root.querySelector('svg');
        pick = root.querySelector('[data-carrier-pick]');
        go = root.querySelector('[data-carrier-go]');
        navigate = vi.fn();
        initCarrier(root, { navigate });
    });

    it('scrubs the slot as the finger drags, without a link yet', () => {
        pointer(svg, 'pointerdown', 10, 'touch');
        expect(root.classList.contains('is-scrubbing')).toBe(true);
        expect(pick.hidden).toBe(false);
        expect(title()).toBe('Signal & 1');
        expect(go.hidden).toBe(true);

        pointer(svg, 'pointermove', 70, 'touch');
        expect(title()).toBe('Signal & 4');
        expect(root.querySelector('[data-carrier-hair]').style.left).toBe('70px');
        expect(root.querySelector('[data-carrier-readout]').hidden).toBe(true);
    });

    it('holds the pick on lift and reveals the link to it', () => {
        pointer(svg, 'pointerdown', 10, 'touch');
        pointer(svg, 'pointermove', 52, 'touch');
        pointer(svg, 'pointerup', 52, 'touch');

        expect(root.classList.contains('is-scrubbing')).toBe(false);
        expect(root.classList.contains('is-held')).toBe(true);
        expect(go.hidden).toBe(false);
        expect(go.getAttribute('href')).toBe('https://example.com/s3/');
    });

    it('never navigates from the chart itself', () => {
        pointer(svg, 'pointerdown', 52, 'touch');
        pointer(svg, 'pointerup', 52, 'touch');
        pointer(svg, 'pointerdown', 52, 'touch');
        pointer(svg, 'pointerup', 52, 'touch');

        expect(navigate).not.toHaveBeenCalled();
    });

    it('ignores finger movement until the finger is down', () => {
        pointer(svg, 'pointermove', 52, 'touch');

        expect(pick.hidden).toBe(true);
    });

    it('clears when the drag reaches the silence, and lifting there holds nothing', () => {
        pointer(svg, 'pointerdown', 90, 'touch');
        pointer(svg, 'pointermove', 150, 'touch');
        expect(pick.hidden).toBe(true);
        expect(svg.querySelector('rect.is-active')).toBeNull();

        pointer(svg, 'pointerup', 150, 'touch');
        expect(root.classList.contains('is-held')).toBe(false);
        expect(go.hidden).toBe(true);
    });

    it('starts a new scrub from a held pick, hiding the link until the next lift', () => {
        pointer(svg, 'pointerdown', 10, 'touch');
        pointer(svg, 'pointerup', 10, 'touch');
        pointer(svg, 'pointerdown', 90, 'touch');

        expect(root.classList.contains('is-held')).toBe(false);
        expect(go.hidden).toBe(true);
        expect(title()).toBe('Signal & 5');
    });

    it('drops the pick when the browser takes the gesture for scrolling', () => {
        pointer(svg, 'pointerdown', 52, 'touch');
        svg.dispatchEvent(new Event('pointercancel'));

        expect(pick.hidden).toBe(true);
        expect(root.classList.contains('is-scrubbing')).toBe(false);
    });

    it('clears a held pick when pressing elsewhere on the page', () => {
        pointer(svg, 'pointerdown', 52, 'touch');
        pointer(svg, 'pointerup', 52, 'touch');
        pointer(document.getElementById('elsewhere'), 'pointerdown', 0, 'touch');

        expect(root.classList.contains('is-held')).toBe(false);
        expect(pick.hidden).toBe(true);
    });

    it('keeps a held pick when pressing inside the carrier (the link)', () => {
        pointer(svg, 'pointerdown', 52, 'touch');
        pointer(svg, 'pointerup', 52, 'touch');
        pointer(go, 'pointerdown', 0, 'touch');

        expect(root.classList.contains('is-held')).toBe(true);
    });
});

// Select mode for the settings lists (tile grids and checklists): off, a tap
// runs the item's own action; on, a tap picks the item, and the action bar
// applies to the picks.
//
// The re-sort hook. Every item is an <li data-key-on data-key-off> whose control
// is a [aria-pressed] button or a checkbox. Ticked items come first, each
// ordered by its data-key-on or data-key-off; a tap moves the item there before
// the server answers. A group heading that sits in the same list as an item is
// an <li data-state="on|off"> with the same keys and no control, so items sort
// around it. A wrapper marked data-resort-group holds one group's items: after a
// move it rewrites the [data-on-count] inside it with the number ticked.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tileSelect', () => ({
        selecting: false,
        picked: [],
        tap(id, action, control, event) {
            if (this.selecting) {
                // A checkbox must not tick while it is only being picked.
                event?.preventDefault();
                this.picked = this.picked.includes(id) ? this.picked.filter((p) => p !== id) : [...this.picked, id];
                return;
            }
            this.resort(control);
            return action();
        },
        resort(control) {
            const controlOf = (li) => li.querySelector('[aria-pressed], input[type="checkbox"]');
            const isOn = (li) => {
                const c = controlOf(li);
                if (!c) return li.dataset.state === 'on';
                return c.matches('input') ? c.checked : c.getAttribute('aria-pressed') === 'true';
            };
            const key = (li) => (isOn(li) ? '0 ' : '1 ') + li.dataset[isOn(li) ? 'keyOn' : 'keyOff'];
            const move = () => {
                // A checkbox has already flipped itself; a button has not.
                if (!control.matches('input')) {
                    control.setAttribute('aria-pressed', control.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                }
                const tile = control.closest('li');
                const next = [...tile.parentElement.children].find((li) => li !== tile && key(li) > key(tile));
                tile.parentElement.insertBefore(tile, next ?? null);
                // Counts live in a group wrapper; a list without one has nothing more to update.
                const group = tile.closest('[data-resort-group]');
                if (!group) return;
                const count = group.querySelector('[data-on-count]');
                const lines = [...group.querySelectorAll('li[data-key-on]')].filter((li) => controlOf(li));
                const on = lines.filter(isOn).length;
                if (count) count.textContent = on;
                const off = group.querySelector('[data-off-count]');
                if (off) off.textContent = lines.length - on;
            };
            document.startViewTransition ? document.startViewTransition(move) : move();
        },
        toggleSelecting() {
            this.selecting = !this.selecting;
            this.picked = [];
        },
        apply(request) {
            request(this.picked).then(() => {
                this.toggleSelecting();
                // A checkbox that was clicked while picking was put back by preventDefault, and the browser then
                // ignores the server's new checked attribute; set each box from its attribute.
                this.$root.querySelectorAll('input[type="checkbox"]').forEach((box) => {
                    box.checked = box.hasAttribute('checked');
                });
            });
        },
    }));
});

// The settings menu marks the section in view: the last one reaching into the
// upper third of the screen, so a section takes over as its heading arrives.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('settingsMenu', () => ({
        current: null,
        init() {
            const visible = new Set();
            const sections = [...document.querySelectorAll('nav[aria-label="Settings sections"] ~ div > section[id]')];
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((e) => (e.isIntersecting ? visible.add(e.target.id) : visible.delete(e.target.id)));
                this.current = sections.findLast((s) => visible.has(s.id))?.id ?? this.current;
            }, { rootMargin: '0px 0px -66% 0px' });
            sections.forEach((s) => observer.observe(s));
        },
    }));
});

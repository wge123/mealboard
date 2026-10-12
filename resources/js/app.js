// Select mode for the settings tile grids: off, a tap runs the tile's own
// action; on, a tap picks the tile, and the action bar applies to the picks.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tileSelect', () => ({
        selecting: false,
        picked: [],
        tap(id, action, button) {
            if (this.selecting) {
                this.picked = this.picked.includes(id) ? this.picked.filter((p) => p !== id) : [...this.picked, id];
                return;
            }
            this.resort(button);
            return action();
        },
        // Flip the tapped tile and slide it to where the server will put it,
        // before the server answers; the server's render then confirms it, or
        // puts it back. Pressed tiles come first, each ordered by the
        // data-key-on or data-key-off its <li> carries.
        resort(button) {
            const key = (li) => {
                const on = li.querySelector('[aria-pressed]').getAttribute('aria-pressed') === 'true';
                return (on ? '0 ' : '1 ') + li.dataset[on ? 'keyOn' : 'keyOff'];
            };
            const move = () => {
                button.setAttribute('aria-pressed', button.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                const tile = button.closest('li');
                const next = [...tile.parentElement.children].find((li) => li !== tile && key(li) > key(tile));
                tile.parentElement.insertBefore(tile, next ?? null);
            };
            document.startViewTransition ? document.startViewTransition(move) : move();
        },
        toggleSelecting() {
            this.selecting = !this.selecting;
            this.picked = [];
        },
        apply(request) {
            request(this.picked).then(() => this.toggleSelecting());
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

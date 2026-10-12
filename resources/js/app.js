// Select mode for the settings tile grids: off, a tap runs the tile's own
// action; on, a tap picks the tile, and the action bar applies to the picks.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tileSelect', () => ({
        selecting: false,
        picked: [],
        tap(id, action) {
            if (!this.selecting) return action();
            this.picked = this.picked.includes(id) ? this.picked.filter((p) => p !== id) : [...this.picked, id];
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

@extends('dblens::layout')

@section('content')
<div x-data="dbLensER({ diagram: {{ Illuminate\Support\Js::from($diagram) }}, fks: {{ Illuminate\Support\Js::from($fks) }} })"
     x-init="init()"
     class="flex flex-col" style="height: calc(100vh - 6rem);">

    {{-- ─── Toolbar ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded shadow-sm border border-slate-200 mb-3 p-2 flex items-center gap-2 flex-wrap">
        <input type="text" x-model="search" @input="centerOnFirstMatch()" placeholder="🔍 Find table…"
               class="flex-1 min-w-[200px] px-3 py-1.5 border border-slate-300 rounded text-sm">
        <button type="button" @click="zoom(0.8)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Zoom out">−</button>
        <span class="text-xs text-slate-500 mono w-12 text-center" x-text="Math.round(scale*100)+'%'"></span>
        <button type="button" @click="zoom(1.25)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Zoom in">+</button>
        <button type="button" @click="resetView()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm" title="Reset view">⌖</button>
        <button type="button" @click="fitAll()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm">Fit all</button>
        <button type="button" @click="autoLayout()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-sm">Auto layout</button>
        <label class="flex items-center gap-1 text-xs ml-2"><input type="checkbox" x-model="onlyKeys"> compact (keys only)</label>
        <span class="text-xs text-slate-500 ml-auto mono">
            {{ count($diagram) }} tables · {{ count($fks) }} FKs
        </span>
    </div>

    {{-- ─── Canvas ──────────────────────────────────────────────── --}}
    <div x-ref="viewport"
         class="flex-1 min-h-0 bg-slate-50 rounded border border-slate-200 relative overflow-hidden"
         @wheel.prevent="onWheel($event)"
         @mousedown="startPan($event)"
         @mousemove.window="onPan($event)"
         @mouseup.window="endPan()">

        <div class="absolute top-0 left-0 origin-top-left will-change-transform"
             :style="`transform: translate(${pan.x}px, ${pan.y}px) scale(${scale});`"
             style="width: 1px; height: 1px;">

            {{-- SVG arrows under the table cards --}}
            <svg :width="canvasW" :height="canvasH"
                 :style="`width: ${canvasW}px; height: ${canvasH}px;`"
                 class="absolute top-0 left-0 pointer-events-none">
                <defs>
                    <marker id="er-arrow"        viewBox="0 0 12 12" refX="11" refY="6" markerWidth="10" markerHeight="10" orient="auto-start-reverse">
                        <path d="M0,0 L12,6 L0,12 L3,6 Z" fill="#7c3aed"/>
                    </marker>
                    <marker id="er-arrow-hot"    viewBox="0 0 12 12" refX="11" refY="6" markerWidth="11" markerHeight="11" orient="auto-start-reverse">
                        <path d="M0,0 L12,6 L0,12 L3,6 Z" fill="#0284c7"/>
                    </marker>
                </defs>
                <template x-for="(arrow, ai) in arrows" :key="ai">
                    <g :opacity="(highlighted === null) || (highlighted === arrow.from || highlighted === arrow.to) ? 1 : 0.18">
                        <circle :cx="arrow.sx" :cy="arrow.sy" r="4"
                                :fill="highlighted === arrow.from || highlighted === arrow.to ? '#0284c7' : '#7c3aed'"/>
                        <path :d="arrow.d"
                              :stroke="highlighted === arrow.from || highlighted === arrow.to ? '#0284c7' : '#7c3aed'"
                              stroke-width="2"
                              fill="none"
                              :marker-end="highlighted === arrow.from || highlighted === arrow.to ? 'url(#er-arrow-hot)' : 'url(#er-arrow)'"/>
                    </g>
                </template>
            </svg>

            {{-- Table cards --}}
            <template x-for="t in tables" :key="t.name">
                <div class="absolute bg-white border-2 rounded-lg shadow-sm text-xs select-none overflow-hidden"
                     :class="[
                        highlighted === t.name ? 'border-sky-500 shadow-lg' : 'border-slate-300',
                        matchesSearch(t) ? '' : 'opacity-30'
                     ]"
                     :style="`left: ${t.x}px; top: ${t.y}px; width: 260px;`"
                     @mousedown.stop="startDragTable($event, t)"
                     @mouseenter="highlighted = t.name"
                     @mouseleave="highlighted = null">

                    <div class="px-2 py-1 bg-slate-800 text-white rounded-t-md flex items-center justify-between cursor-move">
                        <a :href="`{{ url(config('dblens.viewer.path', 'dblens').'/'.$connection.'/t') }}/${t.name}`"
                           @click.stop class="mono truncate hover:text-sky-300" x-text="t.name"></a>
                        <span class="text-[10px] text-slate-400 ml-1" x-text="`${t.columns.length}`"></span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <template x-for="(c, idx) in visibleColumns(t)" :key="c.name">
                            <div class="flex items-start gap-1 px-2 py-0.5">
                                <span class="shrink-0 mono"
                                      :class="c.is_pk ? 'font-bold text-amber-600' : (c.is_fk ? 'text-violet-600' : (c.is_referenced ? 'text-sky-600' : 'text-slate-700'))">
                                    <span x-show="c.is_pk" title="Primary key">🔑</span>
                                    <span x-show="c.is_fk" title="Foreign key">🔗</span>
                                    <span x-show="!c.is_pk && !c.is_fk && c.is_referenced" title="Referenced">🎯</span>
                                </span>
                                <span class="mono break-all flex-1 leading-tight"
                                      :class="c.is_pk ? 'font-bold text-amber-600' : (c.is_fk ? 'text-violet-600' : (c.is_referenced ? 'text-sky-600' : 'text-slate-700'))"
                                      x-text="c.name"></span>
                                <span class="shrink-0 text-slate-400 mono text-[10px] leading-tight pt-0.5"
                                      x-text="shortType(c.type)" :title="c.type"></span>
                            </div>
                        </template>
                        <template x-if="hiddenCount(t) > 0">
                            <div class="px-2 py-0.5 text-center text-slate-400 italic bg-slate-50 cursor-pointer hover:bg-slate-100"
                                 @click="toggleExpand(t.name)">
                                <template x-if="!expanded[t.name]">
                                    <span>· · ·   <span x-text="`${hiddenCount(t)} more columns`"></span></span>
                                </template>
                                <template x-if="expanded[t.name]">
                                    <span>↑ Collapse</span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Help footer --}}
        <div class="absolute bottom-2 right-2 bg-white/90 border border-slate-200 rounded px-3 py-1 text-[10px] text-slate-500 pointer-events-none">
            Scroll = zoom · Drag background = pan · Drag header = move table
        </div>
    </div>
</div>

<script>
function dbLensER(cfg) {
    return {
        rawTables: cfg.diagram,
        rawFks: cfg.fks,
        tables: [],            // [{name, columns, x, y}]
        arrows: [],            // [{d, from, to}]
        pan: { x: 0, y: 0 },
        scale: 1,
        panning: false,
        panStart: null,
        draggingTable: null,
        dragOffset: { x: 0, y: 0 },
        canvasW: 4000,
        canvasH: 3000,
        expanded: {},
        onlyKeys: true,
        search: '',
        highlighted: null,

        init() {
            this.autoLayout();
            this.$watch('onlyKeys', () => this.$nextTick(() => this.computeArrows()));
            this.$watch('expanded', () => this.$nextTick(() => this.computeArrows()));
            this.fitAll();
        },

        matchesSearch(t) {
            const q = (this.search || '').trim().toLowerCase();
            return q === '' || t.name.toLowerCase().includes(q);
        },

        centerOnFirstMatch() {
            const q = (this.search || '').trim().toLowerCase();
            if (q === '') return;
            const t = this.tables.find(x => x.name.toLowerCase().includes(q));
            if (!t) return;
            this.highlighted = t.name;
            const viewport = this.$el.querySelector('.relative.overflow-hidden');
            const w = viewport.clientWidth, h = viewport.clientHeight;
            this.pan.x = w/2 - (t.x + 120) * this.scale;
            this.pan.y = h/2 - (t.y + 80) * this.scale;
        },

        autoLayout() {
            // Simple grid layout, sized by column count
            const cols = Math.ceil(Math.sqrt(this.rawTables.length));
            const cellW = 280, cellH = 360;
            let i = 0;
            this.tables = this.rawTables.map(t => {
                const x = (i % cols) * cellW + 40;
                const y = Math.floor(i / cols) * cellH + 40;
                i++;
                return { ...t, x, y };
            });
            this.canvasW = Math.max(2000, cols * cellW + 100);
            this.canvasH = Math.max(2000, Math.ceil(this.rawTables.length / cols) * cellH + 100);
            this.$nextTick(() => this.computeArrows());
        },

        resetView() { this.pan = {x:0,y:0}; this.scale = 1; },

        fitAll() {
            if (this.tables.length === 0) return;
            const minX = Math.min(...this.tables.map(t => t.x));
            const minY = Math.min(...this.tables.map(t => t.y));
            const maxX = Math.max(...this.tables.map(t => t.x + 240));
            const maxY = Math.max(...this.tables.map(t => t.y + 360));
            const viewport = this.$el.querySelector('.relative.overflow-hidden');
            const w = viewport?.clientWidth || 800;
            const h = viewport?.clientHeight || 600;
            const sx = w / (maxX - minX + 80);
            const sy = h / (maxY - minY + 80);
            this.scale = Math.min(1, Math.min(sx, sy));
            this.pan.x = -minX * this.scale + 40;
            this.pan.y = -minY * this.scale + 40;
        },

        zoom(factor) {
            this.scale = Math.max(0.1, Math.min(3, this.scale * factor));
        },

        onWheel(e) {
            const factor = e.deltaY < 0 ? 1.1 : 0.9;
            // zoom toward cursor
            const rect = e.currentTarget.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;
            const before = this.scale;
            this.scale = Math.max(0.1, Math.min(3, this.scale * factor));
            const ratio = this.scale / before;
            this.pan.x = mx - (mx - this.pan.x) * ratio;
            this.pan.y = my - (my - this.pan.y) * ratio;
        },

        startPan(e) {
            if (e.target.closest('.cursor-move')) return; // table drag handles its own
            this.panning = true;
            this.panStart = { x: e.clientX - this.pan.x, y: e.clientY - this.pan.y };
        },
        viewportRect() {
            return this.$refs.viewport?.getBoundingClientRect() || { left: 0, top: 0 };
        },
        onPan(e) {
            if (this.draggingTable) {
                const t = this.draggingTable;
                const r = this.viewportRect();
                const localX = e.clientX - r.left;
                const localY = e.clientY - r.top;
                // Cursor's offset INSIDE the card was captured at start in
                // *screen pixels*; convert to canvas units by dividing by scale.
                t.x = (localX - this.pan.x) / this.scale - this.dragOffset.x / this.scale;
                t.y = (localY - this.pan.y) / this.scale - this.dragOffset.y / this.scale;
                this.computeArrows();
                return;
            }
            if (! this.panning) return;
            this.pan.x = e.clientX - this.panStart.x;
            this.pan.y = e.clientY - this.panStart.y;
        },
        endPan() {
            this.panning = false;
            this.draggingTable = null;
        },

        startDragTable(e, t) {
            this.draggingTable = t;
            const rect = e.currentTarget.getBoundingClientRect();
            this.dragOffset.x = e.clientX - rect.left;
            this.dragOffset.y = e.clientY - rect.top;
        },

        shortType(t) {
            return String(t).replace(/\(.*\)/, '').toLowerCase();
        },
        isVisibleColumn(c) {
            if (! this.onlyKeys) return true;
            return c.is_key;
        },
        visibleColumns(t) {
            if (this.expanded[t.name]) return t.columns;
            return t.columns.filter(c => this.isVisibleColumn(c));
        },
        hiddenCount(t) {
            return t.columns.length - this.visibleColumns(t).length + (this.expanded[t.name] ? 0 : 0);
        },
        toggleExpand(name) {
            this.expanded = { ...this.expanded, [name]: ! this.expanded[name] };
        },

        computeArrows() {
            // Build column-row index per table after DOM render
            const tableMap = Object.fromEntries(this.tables.map(t => [t.name, t]));
            const arrows = [];
            for (const fk of this.rawFks) {
                const from = tableMap[fk.table];
                const to = tableMap[fk.foreign_table];
                if (! from || ! to) continue;
                // Approximate column positions
                const fromY = from.y + 30 + this.columnYOffset(from, fk.column);
                const toY = to.y + 30 + this.columnYOffset(to, fk.foreign_column);

                // Pick which side of each table to connect
                const fromRight = from.x + 240;
                const fromLeft = from.x;
                const toRight = to.x + 240;
                const toLeft = to.x;
                let sx, sy = fromY, ex, ey = toY;
                if (to.x > from.x) { sx = fromRight; ex = toLeft; }
                else                { sx = fromLeft;  ex = toRight; }
                const dx = Math.abs(ex - sx);
                const cx = dx / 2;
                const d = `M ${sx} ${sy} C ${sx + (ex > sx ? cx : -cx)} ${sy}, ${ex - (ex > sx ? cx : -cx)} ${ey}, ${ex} ${ey}`;
                arrows.push({ d, sx, sy, ex, ey, from: fk.table, to: fk.foreign_table });
            }
            this.arrows = arrows;
        },

        columnYOffset(t, colName) {
            const visible = this.visibleColumns(t);
            const idx = visible.findIndex(c => c.name === colName);
            if (idx === -1) return 8; // not visible (e.g. compact mode hides it) → header
            return idx * 18 + 8;
        },
    }
}
</script>
@endsection

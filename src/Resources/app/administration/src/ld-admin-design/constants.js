/* GENERIERT aus ld-admin-design 1.1.0 (3d22274) – nicht hier ändern, sondern in ~/projekte/sw6-entwicklung-lohndruckerei/ld-admin-design/vorlage und ./sync.sh ausführen. */
export const DESIGNS = ['a', 'b', 'c'];
export const DEFAULT_DESIGN = 'b';

export const STATES = ['ok', 'warn', 'err', 'off'];

export const STATUS_ICONS = {
    ok: 'regular-checkmark-s',
    warn: 'regular-exclamation-triangle',
    err: 'regular-times-circle',
    off: 'regular-ban',
};

/** Kategorie-Farben c1–c4 (in Design B: Cyan, Magenta, Gelb, Key). */
export const CATEGORY_COLORS = ['c1', 'c2', 'c3', 'c4'];

/**
 * Plugin-Markierung (Testmodus): feste Farbe je Plugin-Präfix (DESIGN.md 7.11).
 * Rot ist für „Hilfetext fehlt“ reserviert.
 */
export const MARKER_COLORS = {
    ld: '#0f9d8f', // LieferLotse
    lm: '#0870ff', // Mensura
    lv: '#d97706', // Vectura
    lo: '#633bc6', // Ordo
    lp: '#e5007e', // Proba
    lu: '#2b2b33', // Opus
};
export const MARKER_FALLBACK_COLOR = '#0b6e79';

/**
 * Link-Bestandteile, an denen Menüpunkte und Reiter eines Plugins erkannt werden.
 * Standard: Modulname aus dem Schlüssel (`ld-mensura` → `ld/mensura`, `ld-mensura`).
 */
export const MARKER_ROUTES = {
    ld: ['ld/supplier', 'ld-supplier', 'ld-lieferlotse'],
};

/**
 * Übertragungswege: feste Farbe und festes Icon in allen Plugins.
 */
export const TRANSPORTS = {
    email: { color: 'c1', icon: 'regular-envelope' },
    ftp: { color: 'c2', icon: 'regular-server' },
    sftp: { color: 'c2', icon: 'regular-lock' },
    portal_manual: { color: 'c3', icon: 'regular-globe' },
    rest_api: { color: 'c4', icon: 'regular-code' },
    soap: { color: 'c4', icon: 'regular-code' },
    webhook: { color: 'c4', icon: 'regular-plug' },
};

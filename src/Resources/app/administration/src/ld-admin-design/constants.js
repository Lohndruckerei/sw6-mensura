/* GENERIERT aus ld-admin-design 1.0.0 (1d3d326) – nicht hier ändern, sondern in ~/projekte/sw6-entwicklung-lohndruckerei/ld-admin-design/vorlage und ./sync.sh ausführen. */
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

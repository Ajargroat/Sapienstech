// Standalone Theme Studio entry (/studio).
//
// The studio used to self-run only through the consultant page router, which
// imported `theme-studio.js` and called its default export with the hub
// region. This entry boots the same init directly on the standalone page:
// `shell: true` tells the live-preview controller that the page is already
// the studio — no `body.studio-mode` switch and no tenant-token paint on the
// chrome itself (the platform shell must never inherit tenant theming).
import initThemeStudio from './theme-studio.js';
import '../../css/features/studio-shell.css';

initThemeStudio({ shell: true });

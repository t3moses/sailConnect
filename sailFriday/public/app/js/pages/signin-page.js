/**
 * Sign In Page Module
 * Sign-in is handled by the sailConnect auth service; this page only forwards there.
 */

import { redirectToSignIn } from '../ssoService.js';

redirectToSignIn(new URL('dashboard.html', window.location.href).href);

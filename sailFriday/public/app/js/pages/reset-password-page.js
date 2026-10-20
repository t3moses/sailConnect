/**
 * Reset Password Page Module
 * Password reset is handled by the sailConnect auth service; this page only forwards there.
 */

import { authBaseUrl } from '../ssoService.js';

window.location.href = `${authBaseUrl()}/forgot-password`;

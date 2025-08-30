import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
// Use Axios' XSRF support to avoid stale meta tokens during HMR
// Laravel sends an `XSRF-TOKEN` cookie; Axios reads it and sends `X-XSRF-TOKEN`
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

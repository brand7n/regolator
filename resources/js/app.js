import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import * as webauthnJson from '@github/webauthn-json/browser-ponyfill';
window.webauthnJson = webauthnJson;
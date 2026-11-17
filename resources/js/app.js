import './bootstrap';
import Alpine from 'alpinejs';
import { posSale } from './pos';

window.Alpine = Alpine;
window.posSale = posSale;
Alpine.start();

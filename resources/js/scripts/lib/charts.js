// Registrasi elemen Chart.js sekali di satu tempat.
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Tooltip,
    Legend,
    Filler,
);

ChartJS.defaults.font.family = 'Instrument Sans, ui-sans-serif, system-ui, sans-serif';
ChartJS.defaults.color = '#64748b';

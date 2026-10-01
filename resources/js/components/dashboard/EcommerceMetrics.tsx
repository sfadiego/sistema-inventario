import {
  useServiceDashboardMasVendidos,
  useServiceDashboardMenosVendidos,
  useServiceDashboardTotalVentas,
} from '@/Services/dashboard/useServiceDashboard';
import { ArrowDown, ArrowUp, Cog, HandCoins } from 'lucide-react';
import { DashboardSingleCard } from './SingleCard/DashboardSingleCard';

export default function EcommerceMetrics() {
  // Primer día del mes en hora local (toISOString usa UTC y a fin de mes ya marcaría el mes siguiente)
  const now = new Date();
  const date = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
  const { isLoading: isLoadingTotal, data: dataTotal } = useServiceDashboardTotalVentas({ fecha: date });
  const { isLoading: isLoadingMasVendido, data: dataMasVendido } = useServiceDashboardMasVendidos();
  const { isLoading: isLoadingMenosVendido, data: dataMenosVendido } = useServiceDashboardMenosVendidos();

  const total = !isLoadingTotal && dataTotal?.total ? dataTotal.total : 0;
  const item = !isLoadingMasVendido && dataMasVendido?.length ? dataMasVendido[0] : { producto: '', cantidad: 0 };
  const itemMenos = !isLoadingMenosVendido && dataMenosVendido?.length ? dataMenosVendido[0] : { producto: '', cantidad: 0 };
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
      <DashboardSingleCard title="Ventas del mes" ammount={`$${total} MX`} IconComponent={HandCoins} />
      <DashboardSingleCard badgeNumber={`${item.cantidad} pz`} title="Mas vendido" ammount={item.producto} IconComponent={Cog} BadgeIcon={ArrowUp} />
      <DashboardSingleCard
        badgeNumber={`${itemMenos.cantidad} pz`}
        title="Menos vendido"
        ammount={itemMenos.producto}
        IconComponent={Cog}
        BadgeIcon={ArrowDown}
      />
    </div>
  );
}

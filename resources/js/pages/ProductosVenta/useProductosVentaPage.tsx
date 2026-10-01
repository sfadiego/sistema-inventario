import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

import { BreadcrumbArrayProps } from '@/components/common/breadcrum';
import { IFilters } from '@/components/filters/modalFilter/types';
import { rowTypes } from '@/components/tables/rowTypes';
import Button from '@/components/ui/button/Button';

import { ExpansionProductoDetail } from '@/components/productos/ExpansionProductoDetail';
import { ColumnProperties } from '@/components/tables/columnProperties';
import { StatusVentaEnum } from '@/enums/StatusVentaEnum';
import { useDataTable } from '@/hooks/useDatatable';
import { IProducto } from '@/models/producto.interface';
import { AdminRoutes } from '@/router/modules/admin.routes';
import { useServiceIndexProductos } from '@/Services/productos/useServiceProductos';
import { useServiceVentaProductoDetalle } from '@/Services/ventaProducto/useServiceVentaProducto';
import { useServiceShowVenta } from '@/Services/ventas/useServiceVenta';
import { useParams } from 'react-router';
import { IFiltroProducto } from '../Productos/useProductosPage';
import { useProductoVentaModal } from './useProductoVentaModal';

export interface IFiltroProductoVenta extends IFiltroProducto {
  nombre?: string;
}
const FILTERS: IFilters<IFiltroProductoVenta>[] = [{ property: 'nombre', operator: 'like', initialValue: '' }];

export interface useProductosVentaPageProps {
  ventaId?: number;
}

export const useProductosVentaPage = () => {
  const { id } = useParams();
  const ventaId = id ? Number(id) : 0;
  const [automaticAdd, setautomaticAdd] = useState<boolean>(true);
  const toggleAutomaticAdd = () => setautomaticAdd(!automaticAdd);

  const { data: ventaData, isLoading } = useServiceShowVenta(ventaId);
  const productoVentaModal = useProductoVentaModal();

  const ventaFinalizada = useMemo(() => !isLoading && ventaData?.status_venta === StatusVentaEnum.FINALIZADA, [isLoading, ventaData]);
  // Breadcrumbs
  const breadcrumb: BreadcrumbArrayProps[] = useMemo(
    () => [
      { name: 'Ventas', path: AdminRoutes.Venta, isActive: true },
      { name: 'Productos', path: `/venta/${ventaId}/productos` },
    ],
    [ventaId],
  );

  const columnProperties: ColumnProperties<any> = {
    nombre: {
      width: 300,
      ellipsis: true,
    },
  };
  const rowExpansion = {
    content: ({ record }: { record: IProducto }) => <ExpansionProductoDetail record={record} />,
  };
  // Renderers para la tabla de productos
  const renderersMap = useMemo(() => {
    return {
      rowClassName: ({ stock, cantidad_minima }: IProducto): rowTypes | '' => (cantidad_minima >= stock ? 'redRow' : ''),

      actions: ({ id, stock }: IProducto) =>
        stock > 0 &&
        !ventaFinalizada && (
          <Button onClick={() => productoVentaModal.show(id!)} variant="primary" size="sm">
            <Plus />
          </Button>
        ),
    };
  }, [ventaFinalizada, productoVentaModal]);

  const finished = ventaData?.status_venta == StatusVentaEnum.FINALIZADA;
  const { dataTableProps } = useDataTable({
    service: useServiceVentaProductoDetalle,
    payload: {
      serviceParamId: ventaId,
      filters: [],
    },
  });

  const callbackReadCode = ({ search, data, resetSearch }: { search: string; data: IProducto | IProducto[]; resetSearch: () => void }) => {
    if (!search) return;
    const regex = /^([A-Z0-9]+(-[A-Z0-9]+)*)$/;
    const records = Array.isArray(data) ? data : [data];
    if (records.length === 1 && regex.test(search)) {
      const selectedProduct = records[0];
      const stock = selectedProduct.stock;
      if (selectedProduct?.id && stock > 0) {
        productoVentaModal.show(selectedProduct.id);
        resetSearch();
      }
    }
  };

  return {
    isOpen: productoVentaModal.isOpen,
    openModal: productoVentaModal.show,
    closeModal: productoVentaModal.hide,
    productId: productoVentaModal.productId,
    filters: FILTERS,
    useServiceIndexProductos,
    renderersMap,
    rowExpansion,
    venta: isLoading ? null : (ventaData ?? null),
    breadcrumb,
    columnProperties,
    finished,
    dataTableProps,
    callbackReadCode,
    automaticAdd,
    toggleAutomaticAdd,
  };
};

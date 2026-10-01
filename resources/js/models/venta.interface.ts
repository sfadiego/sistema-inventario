import { StatusVentaType } from '@/types/statusVentaTypes';
import { TipoVentaType } from '@/types/TipoVentaTypes';
import { ICliente } from './cliente.interface';
import { IDevolucion } from './devolucion';

export interface IVenta {
  id: number;
  venta_total: number;
  nombre_venta: string;
  folio: string;
  cliente_id: number | null;
  cliente?: ICliente;
  tipo_compra: TipoVentaType;
  status_venta: StatusVentaType;
  devolucion?: IDevolucion;
  devoluciones?: IDevolucion[];
  created_at?: string;
  /** Solo al crear: fecha en que ocurrió la venta (administradores) */
  fecha?: string;
}

export interface IVentaUpdateProps {
  nombre_venta?: string;
  cliente_id?: number | null;
  tipo_compra?: TipoVentaType;
  status_venta?: StatusVentaType;
}

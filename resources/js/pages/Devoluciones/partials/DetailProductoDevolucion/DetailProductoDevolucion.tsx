import SingleInputField from '@/components/form/input/SingleInputField';
import Label from '@/components/form/Label';
import Badge from '@/components/ui/badge/Badge';
import Button from '@/components/ui/button/Button';
import { IDevolucionProducto } from '@/models/devolucion';
import { PlusCircle } from 'lucide-react';
import { useDetailProductoDevolucion } from './useDetailProductoDevolucion';

interface IDetailProductoDevolucion {
  producto: any;
  hasDevolucion: boolean;
  addProduct: (producto: any) => void;
  validateExist: (producto: IDevolucionProducto) => boolean;
}
export const DetailProductoDevolucion = ({ producto, hasDevolucion, addProduct, validateExist }: IDetailProductoDevolucion) => {
  const { msgValidacion, selected, cantidadMaxima, unidadMetro, handleAgregar, handleChange } = useDetailProductoDevolucion({
    producto,
    addProduct,
    validateExist,
  });
  return (
    <div className="grid grid-cols-12 gap-3 p-4">
      <div className="col-span-12">
        <Label>
          Producto: <span className="break-words">{`${producto?.producto.nombre || ''}`}</span>
        </Label>

        {msgValidacion && (
          <Badge color="warning" variant="light">
            {msgValidacion}
          </Badge>
        )}
      </div>

      <div className="col-span-6">
        <Label>Precio </Label>
        <SingleInputField
          disabled={true}
          name="precio_unitario"
          onChange={(e) => handleChange('precio_unitario', e.target.value ? parseFloat(e.target.value) : 0)}
          value={selected.precio_unitario}
        />
        <SingleInputField name="ventaId" type="hidden" />
      </div>
      <div className="col-span-6">
        <Label>{`Cantidad (máx. ${cantidadMaxima})`}</Label>
        <SingleInputField
          type="number"
          min={unidadMetro ? '0.01' : '1'}
          max={String(cantidadMaxima)}
          step={unidadMetro ? 0.01 : 1}
          disabled={hasDevolucion}
          onChange={(e) => handleChange('cantidad', e.target.value ? parseFloat(e.target.value) : 0)}
          value={selected.cantidad}
          name="cantidad"
        />
      </div>
      {!hasDevolucion && (
        <div className="col-span-2">
          <Label>Agregar </Label>
          <Button onClick={handleAgregar} variant="outline">
            <PlusCircle />
          </Button>
        </div>
      )}
    </div>
  );
};

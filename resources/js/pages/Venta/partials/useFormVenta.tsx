import { IOptions } from '@/components/form/select/interfaces/IOptions';
import { RolesEnum } from '@/enums/RolesEnum';
import { StatusVentaEnum } from '@/enums/StatusVentaEnum';
import { useAxios } from '@/hooks/useAxios';
import { useOnSubmit } from '@/hooks/useOnSubmit';
import { ICliente } from '@/models/cliente.interface';
import { IVenta } from '@/models/venta.interface';
import { AdminRoutes } from '@/router/modules/admin.routes';
import { useServiceShowCliente } from '@/Services/clientes/useServiceClientes';
import { useServiceStoreVenta } from '@/Services/ventas/useServiceVenta';
import { useSelectedItemStore } from '@/store/useSelectedItemStore';
import { TipoVentaEnum } from '@/types/TipoVentaTypes';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router';
import { MultiValue, SingleValue } from 'react-select';
import * as Yup from 'yup';

/** Fecha local de hoy (YYYY-MM-DD); toISOString usaría UTC */
const hoy = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const validationSchema = Yup.object().shape({
  venta_total: Yup.number(),
  folio: Yup.string(),
  nombre_venta: Yup.string().max(100, 'El nombre no puede exceder 100 caracteres'),
  tipo_compra: Yup.string()
    .oneOf([TipoVentaEnum.CONTADO, TipoVentaEnum.CREDITO], 'Tipo de compra es inválido')
    .required('El tipo de compra es obligatorio'),
  cliente_id: Yup.number().when('tipo_compra', {
    is: (tipoCompra: string) => tipoCompra === TipoVentaEnum.CREDITO,
    then: (schema) => schema.required('El cliente es obligatorio'),
    otherwise: (schema) => schema.nullable(),
  }),
  status_venta: Yup.string()
    .oneOf([StatusVentaEnum.ACTIVA, StatusVentaEnum.FINALIZADA], 'Estatus de compra inválido')
    .required('El estatus de compra es obligatorio'),
  fecha: Yup.string()
    .nullable()
    .test('no-futura', 'La fecha no puede ser futura', (value) => !value || value <= hoy()),
});

export const useFormVenta = () => {
  const navigate = useNavigate();
  const { user } = useAxios();
  const puedeElegirFecha = [RolesEnum.ADMIN, RolesEnum.SUPERADMIN].includes(user?.role_id ?? 0);
  const { getItem, setItem, clearItem } = useSelectedItemStore();

  const venta = getItem('venta') as IVenta;
  const cliente = getItem('cliente') as ICliente;

  const [esNuevocliente, setEsNuevocliente] = useState<boolean>(true);
  const [clienteSeleccionado, setClienteSeleccionado] = useState<number>(0);

  const toggleClient = useCallback(
    (formik: any) => {
      const nextValue = !esNuevocliente;
      setEsNuevocliente(nextValue);
      if (!nextValue) {
        clearItem('cliente');
        setClienteSeleccionado(0);
        formik.setFieldValue('cliente_id', null);
      }
    },
    [clearItem, esNuevocliente],
  );

  const resetVenta = useCallback(() => {
    clearItem('venta');
    clearItem('cliente');
    setClienteSeleccionado(0);
    setEsNuevocliente(true);
  }, [clearItem]);

  const redirectNewCliente = useCallback(() => navigate(AdminRoutes.Clientes), [navigate]);

  const handleSuccess = useCallback(
    (venta: IVenta) => {
      setItem('venta', venta);
      navigate(`/venta/${venta?.id}/productos`);
    },
    [navigate, setItem],
  );

  const clienteId = venta?.cliente_id && !esNuevocliente ? venta?.cliente_id : null;
  const initialValues: IVenta = {
    id: venta?.id ?? 0,
    venta_total: venta?.venta_total ?? 0,
    folio: venta?.folio ?? '',
    nombre_venta: venta?.nombre_venta ?? '',
    cliente_id: clienteId,
    tipo_compra: venta?.tipo_compra ?? TipoVentaEnum.CONTADO,
    status_venta: venta?.status_venta ?? StatusVentaEnum.ACTIVA,
    fecha: '',
  };

  const mutator = useServiceStoreVenta();
  const { onSubmit } = useOnSubmit<IVenta>({
    mutateAsync: mutator.mutateAsync,
    onSuccess: handleSuccess,
  });

  /** Cliente seleccionado */
  const { data, isLoading } = useServiceShowCliente(clienteSeleccionado ?? 0);

  useEffect(() => {
    if (clienteSeleccionado > 0 && data) {
      setItem('cliente', data);
    } else if (clienteSeleccionado === 0) {
      clearItem('cliente');
    }
  }, [data, clienteSeleccionado, setItem, clearItem]);

  const confiable = cliente?.id !== undefined ? Number(cliente.confiable) === 1 : true;
  const adeudo = cliente?.adeudo || 0;
  const msgAdeudo = adeudo === 0 ? '' : ` Este cliente tiene un adeudo de ${adeudo}`;

  const isValidClient = useMemo(() => (!isLoading && confiable && adeudo === 0) || esNuevocliente, [isLoading, confiable, adeudo, esNuevocliente]);

  const onChangeValidateCliente = useCallback(
    (option: SingleValue<IOptions> | MultiValue<IOptions>) => {
      if (!option) {
        clearItem('cliente');
        setClienteSeleccionado(0);
      }

      if (option && !Array.isArray(option) && 'value' in option) {
        setClienteSeleccionado(option.value as number);
      }
    },
    [clearItem],
  );

  const formikProps = {
    initialValues,
    validationSchema,
    onSubmit,
  };

  return {
    formikProps,
    isPending: mutator.isPending,
    ventaActual: venta,
    title: venta?.id ? `Venta: ${venta.folio}` : 'Crear Venta',
    total: venta?.venta_total ?? null,
    disabled: !!venta?.id,
    puedeElegirFecha,
    resetVenta,
    esNuevocliente,
    toggleClient,
    redirectNewCliente,
    onChangeValidateCliente,
    isValidClient,
    adeudo,
    msgAdeudo,
    confiable,
  };
};

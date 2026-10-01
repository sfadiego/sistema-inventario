import { AlertSwal } from '@/components/alertSwal/AlertSwal';
import { useOnSubmit } from '@/hooks/useOnSubmit';
import { ICategoria } from '@/models/categoria.interface';
import { IProveedorFormik } from '@/models/proveedor.interface';
import { ApiRoutes } from '@/router/modules/admin.routes';
import { useServiceStoreProveedor, useServiceUpdateProveedor } from '@/Services/proveedor/useServiceProveedor';
import { useSelectedItemStore } from '@/store/useSelectedItemStore';
import { useQueryClient } from '@tanstack/react-query';
import * as Yup from 'yup';

interface IUseProveedorFormProps {
  closeModal?: () => void;
}

export const useProveedorForm = (props: IUseProveedorFormProps) => {
  const { closeModal } = props;
  const { getItem } = useSelectedItemStore();
  const proveedor = getItem('proveedor');

  const initialValues: IProveedorFormik = {
    nombre: proveedor?.nombre ?? '',
    observaciones: proveedor?.observaciones ?? '',
    categorias: (proveedor?.categorias ?? [])
      .filter((c: unknown): c is ICategoria => typeof c === 'object' && c !== null)
      .map((c: ICategoria) => c.id),
  };

  const validationSchema = Yup.object().shape({
    nombre: Yup.string().required('El nombre es obligatorio'),
    observaciones: Yup.string(),
    categorias: Yup.array(),
  });

  const queryClient = useQueryClient();
  const handleSuccess = () => {
    if (closeModal) {
      closeModal();
    }

    AlertSwal({
      title: `Exito`,
      text: `Guardado correctamente`,
    });
    queryClient.invalidateQueries({ queryKey: [ApiRoutes.Proveedores] });
  };
  const mutator = useServiceStoreProveedor();
  const mutatorUpdate = useServiceUpdateProveedor(proveedor?.id ?? 0);
  const { onSubmit } = useOnSubmit<IProveedorFormik>({
    mutateAsync: proveedor?.id ? mutatorUpdate.mutateAsync : mutator.mutateAsync,
    onSuccess: async () => handleSuccess(),
  });

  const formikProps = {
    initialValues,
    validationSchema,
    onSubmit,
  };

  return { formikProps, isPending: mutator.isPending };
};

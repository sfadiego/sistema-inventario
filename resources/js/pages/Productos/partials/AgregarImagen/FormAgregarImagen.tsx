import DropzoneComponent from '@/components/dropzone/DropzoneComponent';
import { Modal } from '@/components/ui/modal';
import { useFormAgregarImagen } from './useFormAgregarImagen';

interface IModalAgregarImagenProps {
  isOpen: boolean;
  closeModal: () => void;
  productId: number;
}

export const FormAgregarImagen = ({ isOpen, closeModal, productId }: IModalAgregarImagenProps) => {
  const { onSubmit } = useFormAgregarImagen({ productId, closeModal });

  return (
    <Modal title={`Producto`} subtitle={`Actualiza imagen de producto`} isOpen={isOpen} onClose={closeModal} className="m-4 max-w-[700px]">
      <div className={`grid grid-cols-12 gap-3`}>
        <div className="col-span-12">
          <DropzoneComponent onSubmitFile={(file) => onSubmit(Array.isArray(file) ? file[0] : file)} />
        </div>
      </div>
    </Modal>
  );
};

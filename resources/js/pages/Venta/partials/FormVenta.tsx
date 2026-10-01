import { InputTypeEnum } from '@/components/form/input/enum/InputType.enum';
import Input from '@/components/form/input/InputField';
import Switch from '@/components/form/switch/Switch';
import { SelectCliente } from '@/components/select/clientes/SelectCliente';
import { SelectStatusVenta } from '@/components/select/statusVenta/SelectStatusVenta';
import { SelectTipoVenta } from '@/components/select/tipoVenta/SelectTipoVenta';
import Badge from '@/components/ui/badge/Badge';
import Button from '@/components/ui/button/Button';
import { ButtonTypeEnum } from '@/components/ui/button/enums/buttonType.enum';
import { Modal } from '@/components/ui/modal';
import { IVenta } from '@/models/venta.interface';
import { AdminRoutes } from '@/router/modules/admin.routes';
import { Form, Formik } from 'formik';
import { Save, UserPlus } from 'lucide-react';
import { Link } from 'react-router';
import { useFormVenta } from './useFormVenta';

interface IFormVentaProps {
  isOpen: boolean;
  closeModal: () => void;
}
export const FormVenta = ({ isOpen, closeModal }: IFormVentaProps) => {
  const {
    formikProps,
    isPending,
    disabled,
    title,
    total,
    resetVenta,
    ventaActual,
    esNuevocliente,
    toggleClient,
    redirectNewCliente,
    onChangeValidateCliente,
    isValidClient,
    adeudo,
    confiable,
    puedeElegirFecha,
  } = useFormVenta();
  const mostrarFecha = puedeElegirFecha && !ventaActual?.id;

  return (
    <Modal isOpen={isOpen} title={title} onClose={closeModal} className="m-4 max-w-[700px]">
      {ventaActual?.id && (
        <div className={`grid grid-cols-12 gap-2 pt-3 pb-5`}>
          <div className="col-span-12">
            <b>Total</b>: {total}
          </div>
        </div>
      )}
      <Formik enableReinitialize {...formikProps}>
        {(formik) => (
          <>
            <Form className={`grid grid-cols-12 gap-2 pt-3 pb-5`}>
              <div className="col-span-12 flex justify-end">
                {!esNuevocliente && (
                  <Button variant="outline" size="sm" className="mr-2" onClick={redirectNewCliente}>
                    <UserPlus />
                  </Button>
                )}

                <Switch
                  disabled={disabled}
                  label={`${!esNuevocliente ? '' : 'No'} Asignar cliente`}
                  defaultChecked={!esNuevocliente}
                  onChange={() => toggleClient(formik)}
                />
              </div>
              <div className={mostrarFecha ? 'col-span-8' : 'col-span-12'}>
                <Input<IVenta> disabled={true} label={`Folio`} name="folio" formik={formik} type={InputTypeEnum.Text} />
              </div>
              {mostrarFecha && (
                <div className="col-span-4">
                  <Input<IVenta> label="Fecha" name="fecha" formik={formik} type={InputTypeEnum.Date} />
                </div>
              )}
              <div className="col-span-12">
                <Input<IVenta> disabled={disabled} label={`Nombre de Venta`} name="nombre_venta" formik={formik} type={InputTypeEnum.Text} />
              </div>
              <div className="col-span-12">
                <SelectTipoVenta disabled={disabled} formik={formik} />
              </div>

              {formik.values.tipo_compra == 'credito' && !formik.values.cliente_id && !ventaActual?.id && (
                <div className="col-span-12">
                  <Badge color="warning" variant="light">
                    Debe seleccionar un cliente para ventas a crédito
                  </Badge>
                </div>
              )}
              <div className="col-span-12">
                <SelectStatusVenta disabled={true} formik={formik} />
              </div>
              {!esNuevocliente && (
                <>
                  <div className="col-span-12 mt-2">
                    <SelectCliente onChange={onChangeValidateCliente} disabled={disabled} formik={formik} />
                    {!confiable && (
                      <div className="col-span-12">
                        <Badge color="warning" variant="light">
                          Cliente marcado como no confiable
                        </Badge>
                      </div>
                    )}
                  </div>
                </>
              )}

              {formik.values.cliente_id && !isValidClient && adeudo < 0 && (
                <div className="col-span-12">
                  <Badge color="error" variant="light">
                    Este cliente tiene un adeudo de ${adeudo}
                  </Badge>
                  <Link className="text-blue-500 underline" to={`${AdminRoutes.Clientes}`}>
                    Ver
                  </Link>
                </div>
              )}

              {!ventaActual?.id && (
                <div className="col-span-12 mt-3 flex justify-end gap-2">
                  <Button
                    className="col-span-6"
                    onClick={() => {
                      resetVenta();
                      closeModal();
                    }}
                    size="sm"
                    variant="outline"
                  >
                    Cancelar
                  </Button>
                  <Button className="col-span-6" size="md" type={ButtonTypeEnum.Submit} disabled={isPending || formik.isSubmitting}>
                    <Save /> Crear venta
                  </Button>
                </div>
              )}
            </Form>
          </>
        )}
      </Formik>
    </Modal>
  );
};

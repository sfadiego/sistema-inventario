import { format } from 'date-fns';
import 'flatpickr/dist/themes/material_green.css';
import { FormikProps } from 'formik';
import { useMemo, useState } from 'react';
import Flatpickr from 'react-flatpickr';
import Label from '../Label';
import { useDatePicker } from './useDatepicker';

interface DatePickerProps {
  name: string;
  formik?: FormikProps<any>;
  label?: string;
  disabled?: boolean;
  mode?: 'single' | 'range';
  initialEndDate?: string;
  initialDate?: string;
  className?: string;
  allowEmpty?: boolean;
  onChange?: (date: any) => void;
}

export default function DatePicker(props: DatePickerProps) {
  const {
    label,
    name,
    formik,
    disabled = false,
    initialDate,
    mode = 'single' as 'single' | 'range' | 'time' | 'multiple',
    initialEndDate,
    className = '',
    allowEmpty = false,
    onChange,
  } = props;

  const { handleSetDate, handleChange } = useDatePicker();

  const isRangeMode = mode === 'range';
  const initialValue = useMemo(() => {
    const currentDate = new Date();
    if (allowEmpty) {
      return isRangeMode ? [] : null;
    } else if (isRangeMode) {
      return [initialDate ? initialDate : format(currentDate, 'yyyy-MM-dd'), initialEndDate ? initialEndDate : format(currentDate, 'yyyy-MM-dd')];
    } else {
      return initialDate ? initialDate : format(currentDate, 'yyyy-MM-dd');
    }
  }, [allowEmpty, isRangeMode, initialDate, initialEndDate]);

  const [date, setDate] = useState<any>(initialValue);

  // Opciones de configuración para Flatpickr
  const flatpickrOptions = {
    dateFormat: 'Y-m-d',
    position: 'auto left' as const,
    mode: isRangeMode ? 'range' : ('single' as 'single' | 'range' | 'time' | 'multiple'),
    allowInput: true,
    enableTime: false,
    disableMobile: true,
    closeOnSelect: !isRangeMode,
  };
  const defaultClassName = `${className != '' ? className : 'shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/20 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30'}`;

  return (
    <div>
      {label && <Label htmlFor={name}>{label}</Label>}
      <Flatpickr
        disabled={disabled}
        value={date}
        options={flatpickrOptions}
        className={defaultClassName}
        onChange={(newDateInputValue: Array<any>) => {
          if (newDateInputValue.length === 0 && allowEmpty) {
            setDate(isRangeMode ? [] : null);
            onChange?.(null);
            formik?.setFieldValue(name, null);
            if (isRangeMode) {
              formik?.setFieldValue(`${name}_end`, null);
            }
            return;
          }

          const newDate = handleChange(newDateInputValue, date);
          if (formik) {
            setDate(handleSetDate(formik, name, newDate, isRangeMode));
          } else {
            setDate(newDate);
          }
          onChange?.(newDate);
        }}
      />
      {formik?.submitCount ? formik.errors[name] ? <span className={`text-error-500 mt-1.5`}>{String(formik.errors[name])}</span> : '' : ''}
    </div>
  );
}

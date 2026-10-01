import { ColumnProperties } from '@/components/tables/columnProperties';
import { DataTableColumn, DataTableProps } from 'mantine-datatable';
import { useState } from 'react';

export type DataTableRenderersMap = {
  [key: string]: (record: any) => any;
} & {
  rowClassName?: (record: any) => string | undefined;
};

interface UseDataTableParams<T = any> {
  service: (params: any) => any;
  payload?: any;
  columnProperties?: ColumnProperties<T>;
  renderersMap?: DataTableRenderersMap;
}
export const useDataTable = ({ service, payload = {}, renderersMap = {}, columnProperties = {} as ColumnProperties<any> }: UseDataTableParams) => {
  const [page, setPage] = useState(1);
  const pageSize = [10, 20, 30, 50, 100];
  const [limit, setLimit] = useState(pageSize[0]);
  const { data, isLoading, refetch } = service({ page, limit, ...payload });

  const applyRenderers = <T,>(columns: DataTableColumn<T>[], columnProperties: ColumnProperties<T> = {}): DataTableColumn<T>[] => {
    return columns.map((column) => {
      const accessor = column.accessor as string;
      const props = columnProperties[accessor] || {};
      return {
        ...column,
        ...props,
        render: accessor && renderersMap[accessor] ? renderersMap[accessor] : undefined,
      } as DataTableColumn<T>;
    });
  };

  const defaultDataTableProps: DataTableProps<any> = {
    page,
    recordsPerPage: data?.per_page ?? limit,
    totalRecords: data?.total || 0,
    onPageChange: setPage,
    records: data?.data || [],
    columns: data?.columns ? applyRenderers<any>(data.columns, columnProperties) : [],
    rowClassName: renderersMap.rowClassName ? renderersMap.rowClassName : undefined,
    onRecordsPerPageChange: setLimit,
    recordsPerPageOptions: pageSize,
    noRecordsText: 'No se encontraron resultados que coincidan con tu búsqueda',
    highlightOnHover: true,
    withTableBorder: true,
    withColumnBorders: true,
    striped: true,
    className: 'whitespace-nowrap table-hover',
    minHeight: 200,
    paginationText: ({ from, to, totalRecords }: { from: number; to: number; totalRecords: number }) =>
      `Mostrando del ${from} al ${to} de ${totalRecords} registros`,
  };

  return {
    page,
    setPage,
    limit,
    setLimit,
    pageSize,
    data,
    isLoading,
    refetch,
    // mantine-datatable declara las props como unión discriminada; se deja sin tipar para poder combinarlas con rowExpansion, etc.
    dataTableProps: defaultDataTableProps as any,
  };
};

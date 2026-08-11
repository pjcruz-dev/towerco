type Column<T> = {
  key: keyof T;
  header: string;
};

type AppTableProps<T extends Record<string, unknown>> = {
  columns: Array<Column<T>>;
  rows: T[];
};

export function AppTable<T extends Record<string, unknown>>({
  columns,
  rows,
}: AppTableProps<T>) {
  return (
    <div className="overflow-hidden rounded-lg border bg-card">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/40 text-left">
            <tr>
              {columns.map((column) => (
                <th key={String(column.key)} className="px-4 py-3 font-medium">
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, rowIndex) => (
              <tr key={rowIndex} className="border-t">
                {columns.map((column) => (
                  <td key={String(column.key)} className="px-4 py-3 text-muted-foreground">
                    {String(row[column.key] ?? "-")}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

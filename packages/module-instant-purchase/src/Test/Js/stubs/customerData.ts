/**
 * Test stub for the customer-data section store. The composable always receives
 * an explicit store in tests, so this only needs to satisfy the import specifier.
 */
export function useCustomerData() {
    return {
        section: (_name: string): unknown => null,
        reload: async (_names?: string[], _options?: { force?: boolean }): Promise<void> => {},
    };
}

export default useCustomerData;

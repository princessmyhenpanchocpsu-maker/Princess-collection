import ProductCard from './ProductCard';

const COLUMNS = {
    3: 'grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
};

// Renders skeleton cards while `loading`, so the page keeps its shape.
export default function ProductGrid({ products = [], loading = false, columns = 4, skeletonCount = 8 }) {
    return (
        <div className={`grid gap-x-4 gap-y-8 sm:gap-x-6 ${COLUMNS[columns]}`} aria-busy={loading}>
            {loading
                ? Array.from({ length: skeletonCount }, (_, index) => (
                      <div key={index} className="animate-pulse" aria-hidden="true">
                          <div className="aspect-[4/5] rounded-2xl bg-blush/50" />
                          <div className="mt-3 h-3 w-1/3 rounded bg-blush/60" />
                          <div className="mt-2 h-4 w-3/4 rounded bg-blush/60" />
                          <div className="mt-2 h-4 w-1/4 rounded bg-blush/60" />
                      </div>
                  ))
                : products.map((product) => <ProductCard key={product.id} product={product} />)}
        </div>
    );
}

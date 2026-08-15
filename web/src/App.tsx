import { AppProviders } from '@/app/providers'
import { AppRouter } from '@/app/routes/AppRouter'

function App() {
  return (
    <AppProviders>
      <AppRouter />
    </AppProviders>
  )
}

export default App
